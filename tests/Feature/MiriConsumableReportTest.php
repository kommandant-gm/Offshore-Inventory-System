<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MiriConstructionItem;
use App\Models\User;
use App\Services\ConsumableInventoryReport;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MiriConsumableReportTest extends TestCase
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

    public function test_bintulu_consumable_monthly_balances_and_template_export(): void
    {
        [$user, $branch] = $this->reader();
        $base = ['branch_id' => $branch, 'category' => ' consumable ', 'section_1' => 'GENERAL', 'description' => '=Training tool & kit', 'current_location' => 'BINTULU NEW YARD', 'unit' => 'PCS', 'stock_balance' => 13, 'unit_price' => 5, 'closing_value' => 65, 'created_at' => '2026-08-01'];
        $item = MiriConstructionItem::create($base);
        foreach ([['category' => 'CONSTRUCTION TEC', 'section_1' => 'CIDB TRAINING'], ['current_location' => 'LABUAN YARD'], ['category' => 'ACCESSORIES', 'section_1' => 'WQT TRAINING'], ['branch_id' => Branch::where('code', 'KL-IT')->value('id')]] as $extra) {
            MiriConstructionItem::create([...$base, ...$extra]);
        }
        foreach ([
            ['opening', 10, null, 10, '2026-08-01 00:00:00'],
            // Malaysia midnight September 1: this receipt belongs to September.
            ['receipt', 5, 10, 15, '2026-08-31 16:00:00'],
            ['issue', -2, 15, 13, '2026-09-03 00:00:00'],
            // Malaysia October 1: excluded from September.
            ['receipt', 4, 13, 17, '2026-09-30 16:00:00'],
        ] as $i => [$kind, $quantity, $before, $after, $date]) {
            DB::table('miri_construction_stock_movements')->insert(['branch_id' => $branch, 'construction_item_id' => $item->id, 'kind' => $kind, 'quantity' => $quantity, 'balance_before' => $before, 'balance_after' => $after, 'unit' => 'PCS', 'location' => 'BINTULU NEW YARD', 'note' => 'Test', 'request_key' => 'test-'.$i, 'user_id' => $user->id, 'created_at' => $date]);
        }
        $this->get(route('miri-reports.bintulu-consumable', ['preview' => 1, 'month' => '2026-09']))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('MiriReports/BintuluConsumable')->has('report.rows', 1)->where('report.rows.0.opening', 10)
            ->where('report.rows.0.received', 5)->where('report.rows.0.issued', 2)->where('report.rows.0.closing', 13)
            ->where('report.rows.0.unit_price', 5)->where('report.rows.0.closing_value', 65));
        $response = $this->get(route('miri-reports.consumable.export', ['month' => '2026-09']))->assertOk()->assertDownload('bintulu-yard-consumable-inventory-report-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $template = new ZipArchive;
        $template->open(resource_path('report-templates/consumable-inventory.xlsx'));
        try {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('GENERAL STORE CONSUMABLE INVENTORY STATUS FOR SEPTEMBER 2026', $xml);
            $this->assertStringContainsString('LOCATION : BINTULU YARD', $xml);
            $this->assertStringContainsString('GRAND TOTAL FOR CONSUMABLE ITEM', $xml);
            $this->assertSame(1, substr_count($xml, 'GRAND TOTAL'));
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertStringContainsString('CONSUMABLE 2026', $zip->getFromName('xl/workbook.xml'));
            $sheet = simplexml_load_string($xml);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('65', (string) $sheet->xpath('//s:c[@r="M33"]/s:v')[0]);
            $this->assertSame('13', (string) $sheet->xpath('//s:c[@r="G33"]/s:v')[0]);
        } finally {
            $zip->close();
            $template->close();
            unlink($path);
        }
        $this->assertSame('13.000', $item->fresh()->stock_balance);
        $this->assertDatabaseCount('miri_construction_stock_movements', 4);
    }

    public function test_missing_history_is_not_inferred_from_imported_receipts_and_historical_prices_are_blank(): void
    {
        [, $branch] = $this->reader();
        MiriConstructionItem::create(['branch_id' => $branch, 'category' => 'CONSUMABLE', 'section_1' => 'WQT TRAINING', 'current_location' => 'BTU', 'stock_balance' => 8, 'stock_in_qty' => 200, 'unit_price' => 9, 'unit' => 'PCS', 'created_at' => '2026-07-01']);
        $service = app(ConsumableInventoryReport::class);
        $current = $service->generate($branch, '2026-09')['rows'][0];
        $this->assertSame(8.0, $current['closing']);
        $this->assertNull($current['received']);
        $this->assertNull($current['opening']);
        $previous = $service->generate($branch, '2026-08')['rows'][0];
        $this->assertNull($previous['closing']);
        $this->assertNull($previous['unit_price']);
    }

    public function test_validation_access_and_missing_database_prerequisites(): void
    {
        [$user] = $this->reader();
        foreach (['miri-reports.bintulu-consumable', 'miri-reports.consumable.export'] as $route) {
            $this->getJson(route($route, ['month' => 'bad']))->assertUnprocessable();
            $this->getJson(route($route, ['month' => '2026-10']))->assertUnprocessable();
            $this->getJson(route($route, ['location' => 'LBN']))->assertUnprocessable();
        }
        \Illuminate\Support\Facades\Schema::drop('miri_construction_stock_movements');
        $this->get(route('miri-reports.bintulu-consumable', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('report.rows', 0)->where('report.unavailable', 'Consumable reporting requires the construction register and stock-history database migrations.'));
        $this->get(route('miri-reports.consumable.export'))->assertUnprocessable();
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['miri-reports.bintulu-consumable', 'miri-reports.consumable.export'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
