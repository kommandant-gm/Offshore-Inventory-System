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
        $item = MiriPaintItem::create(['branch_id' => $branch, 'description' => '=HYPERLINK("bad") & paint', 'section_2' => 'HEMPEL PAINT', 'current_location' => 'BINTULU PAINT STORE', 'opening_litres' => 20.125, 'balance_litres' => 17.125, 'balance_cans' => 0, 'stock_in_qty' => 999, 'unit' => 'LTR', 'closing_unit_price' => 12.5, 'opening_total_price' => 251.56, 'closing_total_price' => 214.06]);
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
            ->where('report.rows.0.unit_price', 12.5)->where('report.rows.0.opening_value', 251.56)->where('report.rows.1.opening_value', null)->where('report.rows.0.adjustments', null)->where('report.rows.1.closing', 0));
        $response = $this->get(route('miri-reports.paint.export', $filters))->assertOk()->assertDownload('paint-inventory-BTU-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with($zip->getNameIndex($i), '.xml') || str_ends_with($zip->getNameIndex($i), '.rels')) {
                    $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
                }
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('=HYPERLINK', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringNotContainsString('Private', $xml);
            $template = new ZipArchive;
            $template->open(resource_path('report-templates/paint-inventory.xlsx'));
            $sheet = simplexml_load_string($xml);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
            $styles->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $remarkStyle = (int) $sheet->xpath('//s:c[@r="Q15"]')[0]['s'];
            $this->assertSame('1', (string) $styles->xpath('//s:cellXfs/s:xf')[''.$remarkStyle]->alignment['wrapText']);
            $this->assertEmpty($sheet->xpath('//s:c[@r="B8"]/s:is'));
            $this->assertSame('1', (string) $sheet->xpath('//s:row[@r="8"]')[0]['hidden']);
            $this->assertSame(1, substr_count($xml, 'GRAND TOTAL'));
            $this->assertStringNotContainsString('Values are recorded item totals', $xml);
            $this->assertStringNotContainsString('Closing balance missing', $xml);
            $this->assertStringContainsString('Opening balance missing', $zip->getFromName('xl/worksheets/report-notes.xml'));
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertStringContainsString('width="53.7109375"', $xml);
            $this->assertStringContainsString('r="I15"', $xml);
            $this->assertStringContainsString('<v>12.5</v>', $xml);
            $this->assertFalse($zip->locateName('xl/calcChain.xml'));
            $template->close();
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
        $this->assertStringContainsString('history unavailable', implode(' ', $service->generate($branch, [...$filters, 'month' => '2026-07'])['notes']));
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

    public function test_combined_report_expands_template_without_losing_rows_or_print_area(): void
    {
        [, $branch] = $this->reader();
        foreach (range(1, 25) as $i) {
            MiriPaintItem::create(['branch_id' => $branch, 'description' => sprintf('Paint %02d', $i),
                'current_location' => $i % 2 ? 'BTU' : 'LBN', 'section_2' => $i % 2 ? 'HEMPEL PAINT' : 'IP',
                'opening_litres' => 2, 'balance_litres' => 1]);
        }
        $this->get(route('miri-reports.index', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('filters.location', 'all')->has('report.rows', 25));
        $response = $this->get(route('miri-reports.paint.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        try {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Paint 25', $xml);
            $this->assertStringContainsString('BINTULU &amp; LABUAN YARDS', $xml);
            $this->assertStringContainsString('r="B40"', $xml);
            $this->assertStringContainsString('r="B44"', $xml);
            $this->assertStringContainsString('$A$1:$Q$47', $zip->getFromName('xl/workbook.xml'));
            $doc = simplexml_load_string($xml);
            $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertCount(25, $doc->xpath('//s:row[@r >= 15 and @r <= 39]/s:c[starts-with(@r,"B")]/s:is'));
            $this->assertSame('25', (string) $doc->xpath('//s:c[@r="G40"]/s:v')[0]);
            $this->assertEmpty($doc->xpath('//s:c[@r="B45"]/s:is')); // No inherited approver names.
        } finally {
            $zip->close();
            unlink($path);
        }
    }
}
