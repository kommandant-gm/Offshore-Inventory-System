<?php
namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriCompanyTest extends TestCase
{
    use RefreshDatabase;
    private function staff(): int {
        $this->withoutVite();
        $user = User::factory()->create(['role'=>'miri','directory_active'=>true,'permissions'=>AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code','MIRI')->value('id');
        $user->branches()->attach($branch,['access_level'=>'edit','is_default'=>true]); $this->actingAs($user);
        return $branch;
    }
    public function test_bulk_assignment_all_registers_changes_only_selected_ownership(): void {
        $branch = $this->staff();
        foreach (['machinery'=>MajorEquipment::class,'cargo'=>MajorEquipment::class,'rental'=>MiriRentalItem::class,'construction'=>MiriConstructionItem::class,'paint'=>MiriPaintItem::class] as $register=>$model) {
            $attributes = ['branch_id'=>$branch,'description'=>'Item'];
            if ($model === MajorEquipment::class) $attributes['inventory_type'] = $register;
            if ($model === MiriPaintItem::class) $attributes['balance_litres'] = 12;
            if ($model === MiriConstructionItem::class) $attributes['stock_balance'] = 20;
            $item = $model::create($attributes); $untouched = $model::create($attributes);
            $this->assertNull($item->company);
            $before = $item->fresh()->getAttributes();
            $this->patch(route('miri-company.assign'), ['register'=>$register,'ids'=>[$item->id],'company'=>'FTSB'])->assertSessionHasNoErrors();
            $this->assertSame('FTSB', $item->fresh()->company); $this->assertNull($untouched->fresh()->company);
            $after = $item->fresh()->getAttributes(); unset($before['company'],$before['updated_at'],$after['company'],$after['updated_at']);
            $this->assertSame($before,$after);
            $this->patch(route('miri-company.assign'), ['register'=>$register,'ids'=>[$item->id],'company'=>'unassigned'])->assertSessionHasNoErrors();
            $this->assertNull($item->fresh()->company);
        }
        $this->assertDatabaseCount('miri_paint_stock_movements',0);
        $this->assertDatabaseHas('audit_logs',['event'=>'company_assigned']);
    }
    public function test_bulk_assignment_rejects_foreign_wrong_register_and_read_only_requests_atomically(): void {
        $branch = $this->staff();
        $item = MajorEquipment::create(['branch_id'=>$branch,'inventory_type'=>'cargo']);
        $other = MajorEquipment::create(['branch_id'=>Branch::where('code','KL-IT')->value('id'),'inventory_type'=>'cargo']);
        $data = ['register'=>'cargo','ids'=>[$item->id,$other->id],'company'=>'DESB'];
        $this->patch(route('miri-company.assign'),$data)->assertSessionHasErrors('ids');
        $this->assertNull($item->fresh()->company);
        $data['ids'] = [$item->id]; $data['register']='machinery';
        $this->patch(route('miri-company.assign'),$data)->assertSessionHasErrors('ids');
        $data['register']='cargo'; $data['company']='Unknown';
        $this->patch(route('miri-company.assign'),$data)->assertSessionHasErrors('company');
        $data['company']='FTSB';
        auth()->user()->update(['permissions'=>array_fill_keys(array_keys(AccessMatrix::modules()),'read')]);
        $this->patch(route('miri-company.assign'),$data)->assertForbidden();
        $this->assertNull($item->fresh()->company);
    }
    public function test_register_dashboard_and_cog_filters_respect_company(): void {
        $branch = $this->staff();
        foreach ([MajorEquipment::class,MiriRentalItem::class,MiriConstructionItem::class,MiriPaintItem::class] as $model) {
            foreach ([null,'DESB','FTSB'] as $company) $model::create(['branch_id'=>$branch,'description'=>'Item','company'=>$company]);
        }
        foreach ([['major-equipment.index','equipment'],['miri-rental.index','rentals'],['construction.index','records'],['paint.index','records']] as [$route,$prop]) {
            $this->get(route($route,['company'=>'FTSB']))->assertOk()->assertInertia(fn (Assert $p)=>$p->where($prop.'.total',1)->where($prop.'.data.0.company','FTSB'));
            $this->get(route($route,['company'=>'unassigned']))->assertOk()->assertInertia(fn (Assert $p)=>$p->where($prop.'.total',1)->where($prop.'.data.0.company',null));
        }
        foreach (['major'=>'summary','rentals'=>'rentalDashboard.summary','construction'=>'constructionDashboard.summary','paint'=>'paintDashboard.summary'] as $view=>$prop) {
            $this->get(route('major-equipment.dashboard',['view'=>$view,'company'=>'FTSB']))->assertOk()->assertInertia(fn (Assert $p)=>$p->where($prop.'.total',1)->where('companyFilter','FTSB'));
        }
        foreach (['Major equipment','Rental','Construction','Paint'] as $type) $this->getJson(route('miri-cogs.items',['type'=>$type,'company'=>'unassigned']))->assertOk()->assertJsonCount(1,'items');
    }
    public function test_register_and_edit_accept_company_without_requiring_it_for_old_records(): void {
        $this->staff();
        $this->post(route('major-equipment.store'),['inventory_type'=>'cargo','category'=>'MAJOR EQUIPMENT','company'=>'FTSB'])->assertSessionHasNoErrors();
        $item = MajorEquipment::firstOrFail(); $this->assertSame('FTSB',$item->company);
        $this->patch(route('major-equipment.update',$item),['inventory_type'=>'cargo','category'=>'MAJOR EQUIPMENT','company'=>'DESB'])->assertSessionHasNoErrors();
        $this->assertSame('DESB',$item->fresh()->company);
        $this->post(route('miri-rental.store'),['status'=>'On Hire','company'=>'FTSB'])->assertSessionHasNoErrors();
        $this->assertSame('FTSB',MiriRentalItem::firstOrFail()->company);
        $this->post(route('construction.store'),['category'=>'PPE','description'=>'Gloves','company'=>'FTSB'])->assertSessionHasNoErrors();
        $this->assertSame('FTSB',MiriConstructionItem::firstOrFail()->company);
        $this->post(route('paint.store'),['category'=>'PAINT','description'=>'Paint','date_status'=>'not_recorded','company'=>'DESB'])->assertSessionHasNoErrors();
        $this->assertSame('DESB',MiriPaintItem::firstOrFail()->company);
    }
}
