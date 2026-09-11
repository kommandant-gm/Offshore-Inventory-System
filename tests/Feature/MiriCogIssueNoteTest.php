<?php
namespace Tests\Feature;
use App\Models\{Branch,User,MajorEquipment,MiriRentalItem,MiriConstructionItem,MiriPaintItem,MiriCog};
use App\Services\MiriCogDocument;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriCogIssueNoteTest extends TestCase
{
    use RefreshDatabase;
    private function staff(): int {
        $this->withoutVite();
        $user=User::factory()->create(['role'=>'miri','directory_active'=>true,'permissions'=>AccessMatrix::permissionsForRole('miri')]);
        $branch=Branch::where('code','MIRI')->value('id');
        $user->branches()->attach($branch,['access_level'=>'edit','is_default'=>true]);$this->actingAs($user);
        return $branch;
    }
    private function payload(array $items): array {
        return ['movement_type'=>'Issue out','document_date'=>'2026-09-11','issued_by_name'=>'Issuer','issued_designation'=>'Storekeeper','issued_date'=>'2026-09-11',
            'verified_by_name'=>'Supervisor','verified_designation'=>'HOD','receiver_name'=>'Receiver','receiver_designation'=>'Technician',
            'consignee_name'=>'Consignee','consignee_department'=>'Operation Dept','from_department'=>'Miri Inventory',
            'from_location'=>'Miri HQ','to_location'=>'Marine Team','destination'=>'Offshore','copy_to'=>'Stores','items'=>$items];
    }
    public function test_all_sources_snapshot_and_never_post_stock(): void {
        $branch=$this->staff();
        $equipment=MajorEquipment::create(['branch_id'=>$branch,'description'=>'Air winch','tag_no'=>'EQ-1','serial_no'=>'SERIAL','model_brand'=>'MODEL','unit'=>'UNIT','quantity'=>5,'current_location'=>'Miri','mr_request'=>'MR-E']);
        $rental=MiriRentalItem::create(['branch_id'=>$branch,'description'=>'Rental compressor','serial_tag_equipment_no'=>'RENTAL-1','unit'=>'UNIT','current_location'=>'Miri','mr_no'=>'MR-R']);
        $construction=MiriConstructionItem::create(['branch_id'=>$branch,'category'=>'PPE','description'=>'Gloves','stock_balance'=>12,'unit'=>'PAIR','tag_no'=>'TEC-1','model_brand'=>'L','serial_no'=>'C-SERIAL','mr_reference'=>'MR-C','personnel_details'=>'PRIVATE IC']);
        $paint=MiriPaintItem::create(['branch_id'=>$branch,'category'=>'PAINT','description'=>'Paint part A','batch_no'=>'BATCH-1','balance_litres'=>20,'mr_reference'=>'MR-P']);
        $models=[$equipment,$rental,$construction,$paint];$types=['Major equipment','Rental','Construction','Paint'];
        $before=array_map(fn ($m)=>$m->fresh()->getRawOriginal(),$models);
        $lines=[];
        foreach ($models as $i=>$model) {
            $this->getJson(route('miri-cogs.items',['type'=>$types[$i]]))->assertOk()->assertJsonCount(1,'items')->assertDontSee('PRIVATE IC');
            $lines[]=['item_type'=>$types[$i],'item_id'=>$model->id,'quantity'=>$i===3?'0.710':1,...($i===3?['unit'=>'LTR']:[])];
        }
        $this->post(route('miri-cogs.store'),$this->payload($lines))->assertRedirect()->assertSessionHasNoErrors();
        $cog=MiriCog::with('items')->firstOrFail();
        $this->assertCount(4,$cog->items);
        $this->assertSame('MODEL',$cog->items[0]->size_model);
        $this->assertSame('SERIAL',$cog->items[0]->serial_no);
        $this->assertSame('MR-P',$cog->items[3]->mr_reference);
        $this->assertNull($cog->items[3]->identifier);
        $this->assertSame('BATCH-1',$cog->items[3]->batch_no);
        $this->assertSame('0.710',$cog->items[3]->quantity);
        foreach ($models as $i=>$model) $this->assertSame($before[$i],$model->fresh()->getRawOriginal());
        $paint->update(['batch_no'=>'CHANGED']);
        $this->assertSame('BATCH-1',$cog->items[3]->fresh()->batch_no);
        $doc=app(MiriCogDocument::class)->data($cog);
        $this->assertSame(['2 UNIT','1 PAIR','0.71 LTR'],$doc['totals']);
        $this->get(route('miri-cogs.show',$cog))->assertOk()->assertInertia(fn(Assert $p)=>$p->has('document.pages',1)->has('document.totals',3));
        $this->get(route('miri-cogs.pdf',$cog))->assertOk()->assertHeader('content-type','application/pdf');
        $pdf=app('dompdf.wrapper')->loadView('miri-cogs.pdf',['cog'=>$cog,'document'=>$doc,'logoPath'=>'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/dayang-logo.png')))])->setPaper('a4','landscape');
        $bytes=$pdf->output(['compress'=>0]);
        $this->assertSame(1,$pdf->getDomPDF()->getCanvas()->get_page_count());
        Storage::disk('local')->put('testing/miri-cog-review.pdf',$bytes);
        fwrite(STDOUT,"\nCOG review PDF: ".Storage::disk('local')->path('testing/miri-cog-review.pdf')."\n");
    }
    public function test_long_documents_paginate_and_keep_all_text(): void {
        $branch=$this->staff();
        $cog=MiriCog::create(['branch_id'=>$branch,'cog_no'=>'MIRI-COG-2026-9999','document_date'=>'2026-09-11','movement_type'=>'Transfer','issued_by_name'=>'Issuer','remarks'=>str_repeat('Document detail ',50)]);
        for($i=0;$i<20;$i++) $cog->items()->create(['branch_id'=>$branch,'item_type'=>'Paint','item_id'=>1,'description'=>'Paint '.$i,'batch_no'=>'BATCH-'.$i,'quantity'=>'0.125','unit'=>'LTR','remarks'=>str_repeat('W',80)]);
        $cog->load('items');$doc=app(MiriCogDocument::class)->data($cog);
        $this->assertGreaterThan(1,count($doc['pages']));
        $this->assertSame(['2.5 LTR'],$doc['totals']);
        $this->assertStringContainsString('DOCUMENT REMARKS:',json_encode($doc['pages']));
        $pdf=app('dompdf.wrapper')->loadView('miri-cogs.pdf',['cog'=>$cog,'document'=>$doc,'logoPath'=>'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/dayang-logo.png')))])->setPaper('a4','landscape');
        $bytes=$pdf->output(['compress'=>0]);
        $this->assertSame(count($doc['pages']),$pdf->getDomPDF()->getCanvas()->get_page_count());
        Storage::disk('local')->put('testing/miri-cog-multipage.pdf',$bytes);
    }
    public function test_paint_units_invalid_ids_and_cross_branch_are_rejected_atomically(): void {
        $branch=$this->staff();
        $paint=MiriPaintItem::create(['branch_id'=>$branch,'category'=>'PAINT']);
        $lines=[['item_type'=>'Paint','item_id'=>$paint->id,'quantity'=>1,'unit'=>'UNIT']];
        $this->post(route('miri-cogs.store'),$this->payload($lines))->assertSessionHasErrors('items.0.unit');
        $this->assertDatabaseCount('miri_cogs',0);
        $other=MiriPaintItem::create(['branch_id'=>Branch::where('code','KL-IT')->value('id'),'category'=>'PAINT','batch_no'=>'SECRET']);
        $this->getJson(route('miri-cogs.items',['type'=>'Paint','search'=>'SECRET']))->assertJsonCount(0,'items');
        $this->post(route('miri-cogs.store'),$this->payload([['item_type'=>'Paint','item_id'=>$other->id,'quantity'=>1,'unit'=>'LTR']]))->assertNotFound();
        $this->assertDatabaseCount('miri_cogs',0);
        $this->getJson(route('miri-cogs.items',['type'=>'Unknown']))->assertUnprocessable();
    }
    public function test_signature_validation_replay_and_readonly_access(): void {
        $branch=$this->staff();
        $cog=MiriCog::create(['branch_id'=>$branch,'cog_no'=>'MIRI-COG-2026-0001','document_date'=>'2026-09-11','movement_type'=>'Issue out']);
        $url=route('miri-cogs.sign',$cog);
        $this->post($url,['signature'=>'https://example.com/image.png'])->assertSessionHasErrors('signature');
        $png=UploadedFile::fake()->image('sign.png',100,50);
        $signature='data:image/png;base64,'.base64_encode(file_get_contents($png->getPathname()));
        $this->post($url,['signature'=>$signature])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('signed',$cog->fresh()->status);
        $this->post($url,['signature'=>$signature])->assertStatus(409);
        auth()->user()->update(['permissions'=>array_fill_keys(array_keys(AccessMatrix::modules()),'read')]);
        $this->get(route('miri-cogs.show',$cog))->assertOk();
        $this->get(route('miri-cogs.create'))->assertForbidden();
        $this->getJson(route('miri-cogs.items',['type'=>'Paint']))->assertForbidden();
        $this->post($url,['signature'=>$signature])->assertForbidden();
    }
}
