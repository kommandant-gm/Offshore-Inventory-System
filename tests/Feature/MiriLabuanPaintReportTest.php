<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MiriCog;
use App\Models\MiriCogItem;
use App\Models\MiriPaintItem;
use App\Models\User;
use App\Services\LabuanPaintInventoryReport;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MiriLabuanPaintReportTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): array
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 24));
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);
        $this->actingAs($user);

        return [$user, $branch];
    }

    public function test_labuan_scope_first_movements_units_and_historical_receipts(): void
    {
        [$user, $branch] = $this->reader();
        $base = ['branch_id' => $branch, 'description' => 'A Paint', 'current_location' => 'LABUAN WAREHOUSE YARD 1', 'unit' => 'CAN', 'stock_in_qty' => 8, 'balance_litres' => 80, 'storage_rack' => 'A1', 'backload_rack' => 'B1', 'created_at' => '2026-08-01'];
        $item = MiriPaintItem::create($base);
        MiriPaintItem::create([...$base, 'description' => 'B Paint', 'current_location' => 'LBN', 'unit' => 'LTR', 'stock_in_qty' => 0]);
        MiriPaintItem::create([...$base, 'current_location' => 'BTU']);
        MiriPaintItem::create([...$base, 'branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        foreach ([['Issue out', 'outbound', -10, 70], ['Issue out', 'outbound', -5, 65], ['Received backload', 'backload', 2, 67]] as $i => [$type, $kind, $qty, $balance]) {
            $cog = MiriCog::create(['branch_id' => $branch, 'cog_no' => 'LBN-'.$i, 'movement_type' => $type, 'document_date' => '2026-09-0'.($i + 1), 'from_location' => 'VESSEL A', 'to_location' => 'VESSEL B', 'status' => 'Confirmed', 'created_by' => $user->id]);
            $line = MiriCogItem::create(['branch_id' => $branch, 'miri_cog_id' => $cog->id, 'item_type' => 'Paint', 'item_id' => $item->id, 'description' => 'Paint', 'quantity' => abs($qty), 'unit' => 'LTR']);
            DB::table('miri_paint_stock_movements')->insert(['branch_id' => $branch, 'paint_item_id' => $item->id, 'cog_item_id' => $line->id, 'kind' => $kind, 'unit' => 'LTR', 'quantity' => $qty, 'balance_after' => $balance, 'period' => '2026-09-01', 'created_at' => '2026-09-0'.($i + 1)]);
        }
        $this->get(route('miri-reports.labuan-paint', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('MiriReports/LabuanPaint')->where('filters.location', 'LBN')->has('report.rows', 2)
            ->where('report.rows.0.received_pc', 8)->where('report.rows.0.received_litres', null)
            ->where('report.rows.0.load_qty', 10)->where('report.rows.0.load_balance', 70)->where('report.rows.0.load_cog', 'LBN-0')
            ->where('report.rows.0.load_date', '01/09/2026')->where('report.rows.0.back_qty', 2)->where('report.rows.0.back_rack', 'B1')
            ->where('report.rows.0.disposal_qty', null)->where('report.rows.1.received_litres', 0));
        $historical = app(LabuanPaintInventoryReport::class)->generate($branch, ['month' => '2026-08', 'brand' => 'all']);
        $this->assertNull($historical['rows'][0]['received_pc']);
        $this->assertNull($historical['rows'][0]['load_qty']);
        $this->assertDatabaseCount('miri_paint_stock_months', 0);
        $this->assertDatabaseCount('miri_paint_stock_movements', 3);
        $this->assertSame('80.000', $item->fresh()->balance_litres);
    }

    public function test_export_preserves_template_and_expands_before_signoff(): void
    {
        [, $branch] = $this->reader();
        foreach (range(1, 40) as $i) {
            MiriPaintItem::create(['branch_id' => $branch, 'description' => sprintf('Paint %02d', $i), 'current_location' => 'LBN', 'po_reference' => '=literal', 'unit' => 'LTR', 'stock_in_qty' => 0]);
        }
        $response = $this->get(route('miri-reports.labuan-paint.export'))->assertOk()->assertDownload('labuan-warehouse-paint-inventory-report-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $template = new ZipArchive;
        $template->open(resource_path('report-templates/labuan-paint-inventory.xlsx'));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with($zip->getNameIndex($i), '.xml') || str_ends_with($zip->getNameIndex($i), '.rels')) {
                    $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
                }
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $sheet = simplexml_load_string($xml);
            $source = simplexml_load_string($template->getFromName('xl/worksheets/sheet1.xml'));
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach (['cols', 'pageSetup', 'pageMargins', 'drawing'] as $part) {
                $this->assertSame($source->$part->asXML(), $sheet->$part->asXML());
            }
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertSame('Paint 40', (string) $sheet->xpath('//s:c[@r="C48"]/s:is/s:t')[0]);
            $this->assertSame('0', (string) $sheet->xpath('//s:c[@r="L9"]/s:v')[0]);
            $this->assertSame('=literal', (string) $sheet->xpath('//s:c[@r="F9"]/s:is/s:t')[0]);
            $this->assertNotEmpty($sheet->xpath('//s:c[@r="A66"]/s:v'));
            $this->assertEmpty($sheet->xpath('//s:row[@r >= 49 and @r <= 65]/s:c/s:v'));
            $this->assertStringContainsString('C48:E48', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringContainsString('$A$1:$AB$69', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('Report month: 2026-09', $zip->getFromName('xl/worksheets/report-notes.xml'));
        } finally {
            $zip->close();
            $template->close();
            unlink($path);
        }
    }

    public function test_validation_authorization_and_missing_history(): void
    {
        [$user] = $this->reader();
        foreach (['miri-reports.labuan-paint', 'miri-reports.labuan-paint.export'] as $route) {
            $this->getJson(route($route, ['location' => 'BTU']))->assertUnprocessable();
            $this->getJson(route($route, ['month' => '2026-10']))->assertUnprocessable();
        }
        \Illuminate\Support\Facades\Schema::drop('miri_paint_stock_movements');
        $this->get(route('miri-reports.labuan-paint', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('report.rows', 0)->where('report.unavailable', 'Paint reporting requires the existing paint register and stock-history database migrations.'));
        $this->get(route('miri-reports.labuan-paint.export'))->assertUnprocessable();
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get(route('miri-reports.labuan-paint'))->assertForbidden();
        $this->get(route('miri-reports.labuan-paint.export'))->assertForbidden();
    }
}
