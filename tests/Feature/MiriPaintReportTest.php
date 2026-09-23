<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MiriCog;
use App\Models\MiriCogItem;
use App\Models\MiriPaintItem;
use App\Models\User;
use App\Services\PaintInventoryReport;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MiriPaintReportTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): array
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 23));
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);
        $this->actingAs($user);

        return [$user, $branch];
    }

    public function test_preview_and_export_are_scoped_and_do_not_change_stock(): void
    {
        [$user, $branch] = $this->reader();
        $item = MiriPaintItem::create(['branch_id' => $branch, 'description' => '=HYPERLINK("bad") & paint', 'section_2' => 'HEMPEL PAINT', 'current_location' => 'BINTULU PAINT STORE', 'opening_litres' => 20.125, 'balance_litres' => 17.125, 'balance_cans' => 0, 'stock_in_qty' => 999]);
        MiriPaintItem::create(['branch_id' => $branch, 'description' => 'Excluded Labuan', 'current_location' => 'LBN', 'section_2' => 'HEMPEL PAINT']);
        MiriPaintItem::create(['branch_id' => $branch, 'description' => 'Excluded IP', 'current_location' => 'BTU', 'section_2' => 'IP']);
        MiriPaintItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'Private', 'current_location' => 'BTU', 'section_2' => 'HEMPEL PAINT']);
        $cog = MiriCog::create(['branch_id' => $branch, 'cog_no' => 'TEST-1', 'movement_type' => 'Issue out', 'document_date' => '2026-09-23', 'status' => 'Draft', 'created_by' => $user->id]);
        $line = MiriCogItem::create(['branch_id' => $branch, 'miri_cog_id' => $cog->id, 'item_type' => 'Paint', 'item_id' => $item->id, 'description' => 'Paint', 'quantity' => 4, 'unit' => 'LTR']);
        foreach ([['outbound', -4, $line->id], ['backload', 1, null], ['adjustment', null, null]] as [$kind, $quantity, $lineId]) {
            DB::table('miri_paint_stock_movements')->insert(['branch_id' => $branch, 'paint_item_id' => $item->id, 'cog_item_id' => $lineId, 'kind' => $kind, 'unit' => 'LTR', 'quantity' => $quantity, 'period' => '2026-09-01', 'created_at' => now()]);
        }
        $filters = ['month' => '2026-09', 'location' => 'BTU', 'brand' => 'Hempel Paint'];
        $this->get(route('miri-reports.index', [...$filters, 'preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('MiriReports/Index')->has('report.rows', 2)->where('report.rows.0.opening', 20.125)
            ->where('report.rows.0.received', null)->where('report.rows.0.issued', 4)->where('report.rows.0.returns', 1)
            ->where('report.rows.0.adjustments', null)->where('report.rows.1.closing', 0));
        $response = $this->get(route('miri-reports.paint.export', $filters))->assertOk()->assertDownload('paint-inventory-BTU-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('=HYPERLINK', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringNotContainsString('Private', $xml);
            $this->assertStringContainsString('<v>20.125</v>', $xml);
            $this->assertStringContainsString('Report notes', $zip->getFromName('xl/workbook.xml'));
        } finally {
            $zip->close();
            unlink($path);
        }
        $this->assertSame('17.125', $item->fresh()->balance_litres);
        $this->assertDatabaseCount('miri_paint_stock_months', 0);
        $this->assertDatabaseCount('miri_paint_stock_movements', 3);
    }

    public function test_historical_snapshots_and_missing_history_are_not_replaced_by_current_balances(): void
    {
        [, $branch] = $this->reader();
        $item = MiriPaintItem::create(['branch_id' => $branch, 'description' => 'Paint', 'current_location' => 'BTU', 'opening_litres' => 900, 'balance_litres' => 800, 'created_at' => '2026-01-01']);
        DB::table('miri_paint_stock_months')->insert(['branch_id' => $branch, 'paint_item_id' => $item->id, 'period' => '2026-08-01', 'opening_litres' => 10, 'closing_litres' => 7, 'created_at' => now()]);
        $service = app(PaintInventoryReport::class);
        $filters = ['month' => '2026-08', 'location' => 'BTU', 'brand' => 'all'];
        $row = $service->generate($branch, $filters)['rows'][0];
        $this->assertSame(10.0, $row['opening']);
        $this->assertSame(7.0, $row['closing']);
        $row = $service->generate($branch, [...$filters, 'month' => '2026-07'])['rows'][0];
        $this->assertNull($row['opening']);
        $this->assertNull($row['closing']);
        $this->assertNull($row['issued']);
        $this->assertStringContainsString('history unavailable', $row['remarks']);
        $item->update(['stock_period' => '2026-08-01']);
        $service->generate($branch, [...$filters, 'month' => '2026-09']);
        $this->assertSame('2026-08-01', $item->fresh()->stock_period);
        $this->assertDatabaseCount('miri_paint_stock_months', 1);
    }

    public function test_filters_and_access_are_validated_for_both_endpoints(): void
    {
        [$user] = $this->reader();
        foreach (['miri-reports.index', 'miri-reports.paint.export'] as $route) {
            foreach ([['month' => '2026-10'], ['month' => 'bad'], ['location' => 'SKA'], ['brand' => 'unknown']] as $filter) {
                $this->getJson(route($route, $filter))->assertUnprocessable();
            }
        }
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['miri-reports.index', 'miri-reports.paint.export'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_missing_stock_history_schema_has_an_explicit_unavailable_state(): void
    {
        $this->reader();
        \Illuminate\Support\Facades\Schema::drop('miri_paint_stock_movements');
        $this->get(route('miri-reports.index', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->has('report.rows', 0)->where('report.unavailable', 'Paint reporting requires the existing paint register and stock-history database migrations.'));
        $this->get(route('miri-reports.paint.export'))->assertUnprocessable();
    }
}
