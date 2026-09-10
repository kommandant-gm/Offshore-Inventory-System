<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\User;
use App\Services\MiriEquipmentCsvService;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriCargoTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->firstOrFail(), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function csv(array $changes = []): UploadedFile
    {
        $header = array_fill(0, 29, '');
        foreach ([0 => 'CATEGORY', 1 => 'SECTION 1', 2 => 'SECTION 2', 3 => 'DESCRIPTION', 4 => 'UNIT', 5 => 'SIZE (MODEL)', 6 => 'SIZE (TON)', 7 => 'SIZE (LENGTH)', 8 => 'QTY', 9 => 'TAG NO (ID NO)', 10 => 'CURRENT LOCATION', 11 => 'STATUS', 12 => 'LOCATION', 15 => 'PADEYE MPI', 17 => 'VISUAL INSPECTION', 19 => 'WIRE SLING VALIDITY'] as $i => $v) $header[$i] = $v;
        $sub = array_fill(0, 29, '');
        $sub[15] = 'CERTIFICATE'; $sub[16] = 'EXPIRY DATE';
        $row = array_replace(array_fill(0, 29, ''), [0 => 'MAJOR EQUIPMENT', 1 => 'CARGO SET', 2 => 'CARGO-EN12238', 3 => 'RUBBISH SKID', 4 => 'UNIT', 5 => '1830(W)x1609(L)x1420(H)', 6 => '7.8T', 7 => '6 METER', 9 => 'DE/SHUC/GR 160', 10 => 'MARINE', 11 => 'NO CERT', 15 => 'CERT-001', 16 => '25-Sep-26', 21 => '198443', 22 => 'Keep remarks', 26 => 'Supplier', 27 => 'Damage report'], $changes);
        $handle = fopen('php://temp', 'w+');
        foreach ([['CATEGORY & SECTION'], $header, $sub, $row, $row, array_fill(0, 29, '')] as $r) fputcsv($handle, $r, ',', '"', '');
        rewind($handle); $contents = stream_get_contents($handle); fclose($handle);
        return UploadedFile::fake()->createWithContent('Cargo.csv', $contents);
    }

    public function test_preview_and_import_preserve_duplicates_and_cargo_fields_and_block_repeat_upload(): void
    {
        $this->staff();
        \Illuminate\Support\Facades\Queue::fake();
        $file = $this->csv();
        $this->postJson(route('major-equipment.import.preview'), ['file' => $file, 'inventory_type' => 'cargo'])
            ->assertOk()->assertJsonPath('records', 2)->assertJsonPath('duplicate_records', 2)->assertJsonPath('samples.0.certificates.0.expiry_date', '2026-09-25');
        $this->post(route('major-equipment.import.store'), ['file' => $file, 'inventory_type' => 'cargo'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, MajorEquipment::count());
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ImportMiriEquipment::class);
        $task = DB::table('miri_import_tasks')->first();
        (new \App\Jobs\ImportMiriEquipment($task->id))->handle(app(MiriEquipmentCsvService::class));
        $this->assertSame('completed', DB::table('miri_import_tasks')->value('status'));
        $this->get(route('major-equipment.import.status', $task->id))->assertOk();
        $this->assertSame(2, MajorEquipment::where('tag_no', 'DE/SHUC/GR 160')->count());
        $item = MajorEquipment::firstOrFail();
        $this->assertSame('cargo', $item->inventory_type);
        $this->assertSame('CARGO-EN12238', $item->section_2);
        $this->assertNull($item->quantity);
        $this->assertSame('NO CERT', $item->status);
        $this->assertSame('7.8T', $item->size_ton);
        $this->assertSame('6 METER', $item->size_length);
        $this->assertSame('Keep remarks', $item->remarks);
        $this->assertSame('Supplier', $item->supplier);
        $this->assertSame('198443', $item->certificates()->where('certificate_type', 'CERTIFICATE OF CONFORMITY')->firstOrFail()->certificate_no);
        $this->postJson(route('major-equipment.import.preview'), ['file' => $file, 'inventory_type' => 'cargo'])->assertJsonPath('already_imported', true);
        $this->post(route('major-equipment.import.store'), ['file' => $file, 'inventory_type' => 'cargo'])->assertSessionHasErrors('file');
        $this->assertSame(2, MajorEquipment::count());
    }

    public function test_duplicates_span_tabs_but_not_branches_and_clear_after_edit(): void
    {
        $this->staff();
        $miri = Branch::where('code', 'MIRI')->value('id');
        $other = Branch::where('code', 'KL-IT')->value('id');
        $cargo = MajorEquipment::create(['branch_id' => $miri, 'inventory_type' => 'cargo', 'category' => 'MAJOR EQUIPMENT', 'tag_no' => ' TAG-1 ', 'description' => 'Cargo', 'current_location' => 'Marine']);
        $machinery = MajorEquipment::create(['branch_id' => $miri, 'category' => 'MAJOR EQUIPMENT', 'tag_no' => 'tag-1', 'description' => 'Winch', 'current_location' => 'Miri']);
        MajorEquipment::create(['branch_id' => $other, 'category' => 'MAJOR EQUIPMENT', 'tag_no' => 'TAG-1']);
        $this->get(route('major-equipment.index', ['inventory_type' => 'cargo', 'quality' => 'duplicates']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('MajorEquipment/Index')->where('equipment.total', 1)->where('summary.duplicates', 1)->where('equipment.data.0.duplicate_count', 2));
        $this->get(route('major-equipment.show', $cargo))->assertInertia(fn (Assert $page) => $page->has('duplicates', 1)->where('duplicates.0.id', $machinery->id));
        $machinery->update(['tag_no' => 'TAG-2']);
        $this->assertSame(0, MajorEquipment::duplicateTag()->count());
    }

    public function test_invalid_date_and_quantity_are_preserved_as_warnings_and_bad_format_is_rejected(): void
    {
        $user = $this->staff();
        $file = $this->csv([16 => '31-Feb-26', 8 => 'unknown']);
        app(MiriEquipmentCsvService::class)->import($file, $user->id, 'cargo');
        $item = MajorEquipment::firstOrFail();
        $this->assertCount(2, $item->import_warnings);
        $this->assertNull($item->quantity);
        $this->assertSame('31-Feb-26', $item->source_values['columns'][16]);
        $this->assertNull($item->certificates()->first()->expiry_date);
        $this->postJson(route('major-equipment.import.preview'), ['file' => $file, 'inventory_type' => 'machinery'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(2, MajorEquipment::count());
    }

    public function test_import_requires_preview_and_permissions(): void
    {
        $user = $this->staff();
        $this->post(route('major-equipment.import.store'), ['file' => $this->csv(), 'inventory_type' => 'cargo'])->assertUnprocessable();
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->postJson(route('major-equipment.import.preview'), ['file' => $this->csv(), 'inventory_type' => 'cargo'])->assertForbidden();
        $this->assertSame(0, MajorEquipment::count());
    }

    public function test_actual_supplied_cargo_file_in_isolated_database_when_available(): void
    {
        $path = 'C:/Users/User/Desktop/Cargo.csv';
        if (! is_file($path)) $this->markTestSkipped('Local supplied CSV is not available.');
        $user = $this->staff();
        $file = new UploadedFile($path, 'Cargo.csv', 'text/csv', null, true);
        $service = app(MiriEquipmentCsvService::class);
        $report = $service->preview($file, 'cargo');
        $summary = $service->import($file, $user->id, 'cargo');
        $this->assertGreaterThan(1700, $summary['created']);
        $this->assertSame($report['records'], MajorEquipment::count());
        $this->assertSame(2, MajorEquipment::where('tag_no', 'DE/SHUC/GR 160')->count());
        $this->assertSame(4, MajorEquipment::whereNotNull('quantity')->count());
        fwrite(STDOUT, "\nCargo file checked: ".json_encode($summary)."\n");
        fwrite(STDOUT, 'Import warnings: '.json_encode(MajorEquipment::whereNotNull('import_warnings')->get(['tag_no', 'import_warnings'])->toArray())."\n");
    }

    public function test_machinery_mapping_remains_compatible_and_registration_pdf_supports_cargo(): void
    {
        $user = $this->staff();
        $header = array_replace(array_fill(0, 32, ''), [0 => 'CATEGORY', 3 => 'DESCRIPTION', 7 => 'TAG NO']);
        $row = array_replace(array_fill(0, 32, ''), [0 => 'MAJOR EQUIPMENT', 1 => 'MACHINARY', 2 => 'AIR WINCH', 3 => 'WINCH', 5 => 'MODEL-1', 6 => 'SERIAL-1', 7 => 'TAG-1', 8 => 'MIRI', 9 => 'IN-USE', 13 => 'CERT-01 25/09/2026', 26 => 'MR-1', 29 => 'SUPPLIER']);
        $file = UploadedFile::fake()->createWithContent('Machinery.csv', implode(',', $header)."\n".implode(',', $row));
        app(MiriEquipmentCsvService::class)->import($file, $user->id, 'machinery');
        $item = MajorEquipment::firstOrFail();
        $this->assertSame('Machinery', $item->section_1);
        $this->assertSame('MODEL-1', $item->model_brand);
        $this->assertSame('SERIAL-1', $item->serial_no);
        $this->assertSame('In Use', $item->status);
        $this->assertSame('MR-1', $item->mr_request);
        $this->assertSame('2026-09-25', $item->certificates()->first()->expiry_date->format('Y-m-d'));
        $this->post(route('major-equipment.store'), ['inventory_type' => 'cargo', 'category' => 'MAJOR EQUIPMENT', 'section_1' => 'CARGO SET', 'section_2' => 'CARGO - DNV', 'description' => 'Cargo item', 'tag_no' => 'TAG-1', 'size_model' => '1800x3000', 'size_ton' => '10T', 'quantity' => '20'])->assertSessionHasNoErrors()->assertRedirect();
        $cargo = MajorEquipment::where('inventory_type', 'cargo')->firstOrFail();
        $this->assertSame('20.00', $cargo->quantity);
        $response = $this->get(route('major-equipment.pdf', $cargo));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
