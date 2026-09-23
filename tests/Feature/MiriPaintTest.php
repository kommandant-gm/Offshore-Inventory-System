<?php
namespace Tests\Feature;

use App\Jobs\ImportMiriPaint;
use App\Models\{AuditLog, Branch, MiriPaintItem, User};
use App\Services\PaintCsvService;
use App\Support\{AccessMatrix, PaintFields};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriPaintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }

    private function staff(bool $edit = true): int
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => $edit ? AccessMatrix::permissionsForRole('miri') : array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => $edit ? 'edit' : 'read', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }
    private function csv(array $changes = [], bool $badMiddle = false): UploadedFile
    {
        $top = array_fill(0, 354, '');
        foreach ([0 => 'CATEGORY & SECTION', 13 => 'PAINT DATA', 15 => 'ISSUE OUT TO LOCATION', 20 => 'MR REQUEST',
            21 => 'PURCHASE ORDER', 23 => 'RECEIVE (STOCK-IN)', 26 => 'RECEIVED BACKLOAD', 30 => 'UNFIT & WRITE-OFF'] as $i => $label) $top[$i] = $label;
        $middle = array_fill(0, 354, ''); $middle[4] = $badMiddle ? 'CLOSING STOCK' : 'OPENING STOCK'; $middle[8] = 'CLOSING STOCK';
        $header = array_pad(array_column(PaintFields::FIELDS, 'label'), 354, '');
        $row = array_replace(array_fill(0, 354, ''), [0 => 'PAINT & GARNET', 1 => 'PAINT', 2 => 'HEMPEL PAINT', 3 => 'Paint sample',
            4 => '2', 5 => '10', 6 => '5.50', 7 => '11', 8 => '0', 9 => '', 10 => '6.50', 11 => '0',
            12 => 'Yard', 13 => 'B001', 14 => '23.11.2026', 15 => 'Marine', 16 => 'Painting work',
            17 => '1', 18 => '5', 19 => '5.50', 20 => 'MR', 21 => 'PO', 22 => "DO\n01/02/26",
            23 => 'CAN', 24 => '3', 25 => 'IN-RACK', 26 => 'Offshore', 27 => 'BACK-COG', 28 => '4', 29 => 'BACK-RACK',
            30 => 'REPORT', 31 => 'WRITE-OFF'], $changes);
        $handle = fopen('php://temp', 'w+');
        foreach ([$top, $middle, $header, $row, array_fill(0, 354, '')] as $line) fputcsv($handle, $line, ',', '"', '');
        rewind($handle); $bytes = stream_get_contents($handle); fclose($handle);
        return UploadedFile::fake()->createWithContent('Paint.csv', $bytes);
    }
    public function test_stock_cards_sum_filtered_snapshots_and_keep_missing_values_distinct_from_zero(): void
    {
        $branch = $this->staff();
        foreach (range(1, 26) as $i) MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT',
            'description' => 'Included', 'section_2' => 'HEMPEL', 'opening_cans' => 1, 'opening_litres' => 0.5,
            'opening_unit_price' => $i === 1 ? 0 : 20, 'opening_total_price' => 10,
            'balance_cans' => 0, 'closing_total_price' => 0]);
        MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Blank', 'section_2' => 'HEMPEL']);
        MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Excluded',
            'section_2' => 'OTHER', 'opening_total_price' => 999]);
        MiriPaintItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'),
            'category' => 'PAINT', 'section_2' => 'HEMPEL', 'opening_total_price' => 9999]);
        foreach ([1, 2] as $page) {
            $this->get(route('paint.index', ['section_2' => 'HEMPEL', 'page' => $page]))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where('stockSummary.records', 27)
                    ->where('stockSummary.paint_types.hempel.records', 27)
                    ->where('stockSummary.paint_types.hempel.opening_litres', fn ($v) => (float) $v === 13.0)
                    ->where('stockSummary.paint_types.ip.records', 0)
                    ->where('stockSummary.opening_cans', fn ($v) => (float) $v === 26.0)
                    ->where('stockSummary.opening_litres', fn ($v) => (float) $v === 13.0)
                    ->where('stockSummary.opening_total_price', fn ($v) => (float) $v === 260.0)
                    ->where('stockSummary.opening_total_price_count', 26)
                    ->where('stockSummary.opening_unit_price_min', fn ($v) => (float) $v === 0.0)
                    ->where('stockSummary.opening_unit_price_max', fn ($v) => (float) $v === 20.0)
                    ->where('stockSummary.closing_total_price', fn ($v) => $v !== null && (float) $v === 0.0)
                    ->where('stockSummary.balance_litres', null)->where('stockSummary.balance_litres_count', 0)
                    ->where('stockSummary.closing_unit_price_min', null));
        }
        $this->get(route('paint.index', ['search' => 'no-match']))->assertInertia(fn (Assert $p) => $p
            ->where('stockSummary.records', 0)->where('stockSummary.opening_total_price', null)
            ->where('stockSummary.opening_total_price_count', 0));
    }

    public function test_register_closing_stock_is_split_by_location_and_brand_across_filtered_pages(): void
    {
        $branch = $this->staff();
        foreach (range(1, 26) as $i) MiriPaintItem::create(['branch_id' => $branch, 'company' => 'DESB', 'category' => 'PAINT',
            'description' => 'Included', 'section_2' => 'HEMPEL PAINT', 'current_location' => 'BINTULU PAINT STORE - RACK 4C',
            'opening_litres' => 100, 'balance_litres' => 0.5]);
        foreach ([['BTU', 'IP', 0], ['LBN PAINT STORE', 'HEMPEL', null], ['LABUAN STORE', 'INTERNATION PAINT', 4], ['Unknown', 'HEMPEL', 99]] as [$location, $brand, $balance]) {
            MiriPaintItem::create(['branch_id' => $branch, 'company' => 'DESB', 'category' => 'PAINT', 'description' => 'Included',
                'current_location' => $location, 'section_2' => $brand, 'balance_litres' => $balance]);
        }
        foreach ([['FTSB', $branch], ['DESB', Branch::where('code', 'KL-IT')->value('id')]] as [$company, $branchId]) {
            MiriPaintItem::create(['branch_id' => $branchId, 'company' => $company, 'section_2' => 'HEMPEL', 'current_location' => 'BTU', 'balance_litres' => 999]);
        }
        foreach ([1, 2] as $page) $this->get(route('paint.index', ['company' => 'DESB', 'search' => 'Included', 'page' => $page]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('closingStockSummary.locations.0.label', 'BTU')
                ->where('closingStockSummary.locations.0.paints.0.label', 'Hempel Paint')
                ->where('closingStockSummary.locations.0.paints.0.litres', fn ($v) => (float) $v === 13.0)
                ->where('closingStockSummary.locations.0.paints.0.records', 26)
                ->where('closingStockSummary.locations.0.paints.1.litres', fn ($v) => $v !== null && (float) $v === 0.0)
                ->where('closingStockSummary.locations.1.paints.0.litres', null)
                ->where('closingStockSummary.locations.1.paints.1.litres', fn ($v) => (float) $v === 4.0)
                ->where('closingStockSummary.excluded_records', 1));
        $this->get(route('paint.index', ['search' => 'no matching stock']))->assertInertia(fn (Assert $p) => $p
            ->where('closingStockSummary.locations.0.paints.0.litres', null)
            ->where('closingStockSummary.locations.0.paints.0.records', 0));
    }

    public function test_dates_are_split_only_when_unambiguous_and_single_dates_never_assumed(): void
    {
        $service = app(PaintCsvService::class);
        foreach (['05/08/2025-05/08/2027', '05/08/2025 – 05/08/2027', '05.08.2025—05.08.2027'] as $value) {
            $dates = $service->dates($value);
            $this->assertSame('2025-08-05', $dates['manufacture_date']);
            $this->assertSame('2027-08-05', $dates['best_before_date']);
            $this->assertSame('confirmed', $dates['date_status']);
        }
        foreach (['23.11.2026', '31.02.2026', '05/08/2027-05/08/2025', 'garbage', '31/02/2025-05/08/2027'] as $value) {
            $dates = $service->dates($value);
            $this->assertSame('unconfirmed', $dates['date_status']);
            $this->assertNull($dates['best_before_date']);
            $this->assertNull($dates['manufacture_date']);
        }
        $this->assertSame('2026-11-23', $service->dates('23.11.2026')['unconfirmed_date']);
        $this->assertSame('not_recorded', $service->dates(null)['date_status']);
    }
    public function test_preview_job_and_same_file_protection_preserve_every_group_without_stock_replay(): void
    {
        $this->staff(); Queue::fake(); Storage::fake('paint');
        $file = $this->csv();
        $this->postJson(route('paint.import.preview'), ['file' => $file])->assertOk()->assertJsonPath('records', 1)->assertJsonPath('unconfirmed_dates', 1);
        $this->post(route('paint.import.store'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        Queue::assertPushed(ImportMiriPaint::class);
        $this->assertDatabaseCount('miri_paint_items', 0);
        $task = DB::table('miri_paint_imports')->first();
        $job = new ImportMiriPaint($task->id);
        $job->handle(app(PaintCsvService::class)); $job->handle(app(PaintCsvService::class));
        $this->assertDatabaseCount('miri_paint_items', 1);
        $item = MiriPaintItem::firstOrFail();
        foreach (['opening_cans' => '2.000', 'opening_litres' => '10.000', 'opening_unit_price' => '5.50', 'opening_total_price' => '11.00',
            'balance_cans' => '0.000', 'balance_litres' => null, 'closing_unit_price' => '6.50', 'closing_total_price' => '0.00',
            'issue_cans' => '1.000', 'issue_litres' => '5.000', 'issue_total_price' => '5.50', 'stock_in_qty' => '3.000',
            'backload_qty' => '4.000', 'storage_rack' => 'IN-RACK', 'backload_rack' => 'BACK-RACK', 'issue_cog' => 'Painting work',
            'backload_cog' => 'BACK-COG', 'mr_reference' => 'MR', 'po_reference' => 'PO', 'do_reference' => "DO\n01/02/26",
            'unfit_report' => 'REPORT', 'writeoff_reference' => 'WRITE-OFF', 'original_date' => '23.11.2026', 'date_status' => 'unconfirmed'] as $key => $value) $this->assertSame($value, $item->$key);
        $this->assertNull($item->best_before_date);
        $this->assertCount(32, $item->source_values);
        $this->assertStringNotContainsString('Paint sample', DB::table('miri_paint_items')->value('source_values'));
        Storage::disk('paint')->assertMissing($task->file_path);
        $this->get(route('paint.import.status', $task->id))->assertOk()->assertInertia(fn (Assert $p) => $p->where('task.status', 'completed'));
        $this->postJson(route('paint.import.preview'), ['file' => $file])->assertJsonPath('already_imported', true);
        $this->post(route('paint.import.store'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('miri_construction_items', 0);
        $this->assertDatabaseCount('miri_inventory_items', 0);
        $this->assertDatabaseHas('audit_logs', ['module' => 'miri_paint', 'event' => 'imported']);
    }
    public function test_staff_can_confirm_either_date_with_note_and_expiry_filters_exclude_unconfirmed(): void
    {
        $branch = $this->staff();
        $this->travelTo(now()->setDate(2026, 9, 10)->startOfDay());
        $item = MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Single date',
            'original_date' => '01.01.2026', 'unconfirmed_date' => '2026-01-01', 'date_status' => 'unconfirmed']);
        $url = route('paint.index');
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('summary.expired', 0)->where('summary.unconfirmed_dates', 1));
        $data = ['category' => 'PAINT', 'description' => 'Single date', 'date_status' => 'confirmed', 'best_before_date' => '2026-01-01'];
        $this->patch(route('paint.update', $item), $data)->assertSessionHasErrors('review_note');
        $this->patch(route('paint.update', $item), [...$data, 'review_note' => 'Verified label: best before.', 'original_date' => 'forged'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('01.01.2026', $item->fresh()->original_date);
        $this->get($url.'?quality=expired')->assertInertia(fn (Assert $p) => $p->where('records.total', 1)->where('summary.expired', 1));
        $this->patch(route('paint.update', $item), [...$data, 'manufacture_date' => '2027-01-01', 'review_note' => 'Invalid range'])->assertSessionHasErrors('best_before_date');
        $this->patch(route('paint.update', $item), [...$data, 'best_before_date' => null, 'manufacture_date' => '2026-01-01', 'review_note' => 'Corrected: manufacturing date.'])->assertSessionHasNoErrors();
        $this->get($url.'?quality=expired')->assertInertia(fn (Assert $p) => $p->where('records.total', 0));
        $this->patch(route('paint.update', $item), [...$data, 'date_status' => 'unconfirmed'])->assertSessionHasErrors('date_status');
        $this->patch(route('paint.update', $item), [...$data, 'date_status' => 'not_recorded', 'best_before_date' => null, 'review_note' => 'Hide source'])->assertSessionHasErrors('date_status');
        MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Due', 'date_status' => 'confirmed', 'best_before_date' => '2026-09-10']);
        $this->get($url.'?quality=due_30_days')->assertInertia(fn (Assert $p) => $p->where('records.total', 1)->where('summary.due_30_days', 1));
        $this->assertTrue(AuditLog::where('module', 'miri_paint')->where('event', 'updated')->exists());
    }
    public function test_invalid_headers_extra_columns_and_decimal_comma_are_handled(): void
    {
        $branch = $this->staff(); $service = app(PaintCsvService::class);
        $this->postJson(route('paint.import.preview'), ['file' => $this->csv([], true)])->assertUnprocessable();
        $this->postJson(route('paint.import.preview'), ['file' => $this->csv([354 => 'extra'])])->assertUnprocessable();
        $file = $this->csv([5 => '0,71', 8 => '-5', 14 => '31/02/2026']);
        $preview = $service->preview($file, $branch);
        $this->assertCount(2, $preview['conversions']);
        $row = iterator_to_array($service->rows($file))[0];
        $this->assertSame('0.71', $row['opening_litres']);
        $this->assertNull($row['balance_cans']);
        $this->assertSame('0,71', $row['source_values'][5]);
        $this->assertSame('unconfirmed', $row['date_status']);
        $this->post(route('paint.import.store'), ['file' => $file])->assertUnprocessable();
    }
    public function test_duplicate_matching_is_branch_scoped_not_batch_only_and_edit_updates_it(): void
    {
        $branch = $this->staff();
        $base = ['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Product A', 'batch_no' => 'B001', 'current_location' => 'Yard'];
        $one = MiriPaintItem::create($base);
        MiriPaintItem::create([...$base, 'batch_no' => ' b001 ', 'description' => 'product a']);
        MiriPaintItem::create([...$base, 'description' => 'Product B']);
        MiriPaintItem::create([...$base, 'current_location' => 'Other yard']);
        MiriPaintItem::create([...$base, 'branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $this->get(route('paint.index', ['quality' => 'duplicates']))->assertInertia(fn (Assert $p) => $p->where('records.total', 2)->where('records.data.0.duplicate_count', 2));
        $this->patch(route('paint.update', $one), ['category' => 'PAINT', 'description' => 'Product C', 'date_status' => 'not_recorded'])->assertSessionHasNoErrors();
        $this->assertSame(0, MiriPaintItem::duplicateBatch()->count());
        $this->get(route('paint.index'))->assertInertia(fn (Assert $p) => $p->where('records.total', 4));
    }
    public function test_read_only_and_other_branch_access_are_restricted(): void
    {
        $branch = $this->staff(false);
        $item = MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'source_values' => ['Original private snapshot']]);
        $this->get(route('paint.show', $item))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('record.original_values'))->assertDontSee('Original private snapshot');
        $this->get(route('paint.edit', $item))->assertForbidden();
        $this->post(route('paint.store'), [])->assertForbidden();
        $this->postJson(route('paint.import.preview'), ['file' => $this->csv()])->assertForbidden();
        $other = MiriPaintItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'category' => 'PAINT']);
        $this->get(route('paint.show', $other))->assertNotFound();
    }
    public function test_failed_job_rolls_back_and_releases_file_for_retry(): void
    {
        $this->staff(); Queue::fake(); Storage::fake('paint');
        $file = $this->csv();
        $this->postJson(route('paint.import.preview'), ['file' => $file])->assertOk();
        $this->post(route('paint.import.store'), ['file' => $file])->assertRedirect();
        $task = DB::table('miri_paint_imports')->first();
        $service = \Mockery::mock(PaintCsvService::class);
        $service->shouldReceive('import')->once()->andReturnUsing(function ($file, $branch) {
            MiriPaintItem::create(['branch_id' => $branch, 'category' => 'ROLLBACK']);
            throw ValidationException::withMessages(['file' => 'Test failure.']);
        });
        try { (new ImportMiriPaint($task->id))->handle($service); $this->fail('Expected failure'); } catch (ValidationException) {}
        $this->assertDatabaseCount('miri_paint_items', 0);
        $this->assertSame('failed', DB::table('miri_paint_imports')->value('status'));
        $this->assertNull(DB::table('miri_paint_imports')->value('active_hash'));
        Storage::disk('paint')->assertMissing($task->file_path);
        $this->postJson(route('paint.import.preview'), ['file' => $file])->assertJsonPath('already_imported', false);
        $this->staff();
        $this->get(route('paint.import.status', $task->id))->assertNotFound();
    }
    public function test_supplied_csv_imports_154_rows_preserving_originals_in_test_database_only(): void
    {
        $path = 'C:/Users/User/Desktop/Paint.csv';
        if (! is_file($path)) $this->markTestSkipped('Supplied CSV unavailable.');
        $branch = $this->staff(); $service = app(PaintCsvService::class);
        $file = new UploadedFile($path, 'Paint.csv', 'text/csv', null, true);
        $preview = $service->preview($file, $branch);
        $this->assertSame(154, $preview['records']);
        $this->assertSame(4, $preview['duplicate_records']);
        $this->assertSame(71, $preview['categories']['INTERNATION PAINT']);
        $this->assertSame(83, $preview['categories']['HEMPEL PAINT']);
        $result = DB::transaction(fn () => $service->import($file, $branch));
        $this->assertSame(154, $result['created']);
        $this->assertSame(71, MiriPaintItem::whereNotNull('batch_no')->count());
        $this->assertSame(11, MiriPaintItem::whereNotNull('balance_cans')->count());
        $this->assertSame(48, MiriPaintItem::whereNotNull('balance_litres')->count());
        $this->assertSame(85, MiriPaintItem::where('date_status', 'not_recorded')->count());
        $this->assertSame(21, MiriPaintItem::where('date_status', 'unconfirmed')->count());
        $this->assertSame(48, MiriPaintItem::where('date_status', 'confirmed')->count());
        $this->assertSame('0.710', MiriPaintItem::where('source_row', 10)->firstOrFail()->opening_litres);
        $this->assertSame('0,71', MiriPaintItem::where('source_row', 10)->firstOrFail()->source_values[5]);
        $this->assertSame(0, MiriPaintItem::where('date_status', 'unconfirmed')->whereNotNull('best_before_date')->count());
        $this->get(route('paint.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('records.total', 154)->has('records.data', 25));
        fwrite(STDOUT, "\nPaint CSV test DB only: ".json_encode(collect($preview)->except(['samples', 'date_reviews', 'conversions', 'file_hash'])->all())."\n");
    }
}
