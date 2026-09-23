<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MiriCog;
use App\Models\MiriCogItem;
use App\Models\MiriConstructionItem;
use App\Models\User;
use App\Services\LabuanConsumableInventoryReport;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MiriLabuanConsumableReportTest extends TestCase
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

    public function test_labuan_consumable_and_ppe_scope_and_monthly_in_out_details(): void
    {
        [$user, $branch] = $this->reader();
        $base = ['branch_id' => $branch, 'category' => 'CONSUMABLE', 'description' => 'A Consumable', 'current_location' => 'GENERAL STORE LABUAN YARD 1', 'unit' => 'PCS', 'storage_rack' => 'RACK A', 'stock_balance' => 14, 'created_at' => '2026-08-01'];
        $item = MiriConstructionItem::create($base);
        MiriConstructionItem::create([...$base, 'category' => ' ppe ', 'description' => 'B PPE', 'current_location' => 'LBN']);
        foreach ([['category' => 'ACCESSORIES'], ['current_location' => 'BINTULU YARD'], ['branch_id' => Branch::where('code', 'KL-IT')->value('id')]] as $extra) {
            MiriConstructionItem::create([...$base, ...$extra]);
        }
        $cog = MiriCog::create(['branch_id' => $branch, 'cog_no' => 'REPORT-TEST', 'movement_type' => 'Issue out', 'document_date' => '2026-09-03', 'from_location' => 'SUPPLIER YARD', 'to_location' => 'VESSEL A', 'status' => 'Confirmed', 'created_by' => $user->id]);
        $line = MiriCogItem::create(['branch_id' => $branch, 'miri_cog_id' => $cog->id, 'item_type' => 'Construction', 'item_id' => $item->id, 'description' => 'A Consumable', 'quantity' => 2, 'unit' => 'PCS']);
        foreach ([
            ['opening', 10, 10, '2026-08-01 00:00:00'], ['receipt', 5, 15, '2026-08-31 16:00:00'],
            ['backload', 2, 17, '2026-09-02 00:00:00'], ['issue', -2, 15, '2026-09-03 00:00:00'],
            ['writeoff', -1, 14, '2026-09-04 00:00:00'], ['receipt', 100, 114, '2026-09-30 16:00:00'],
        ] as $i => [$kind, $quantity, $after, $date]) {
            DB::table('miri_construction_stock_movements')->insert(['branch_id' => $branch, 'construction_item_id' => $item->id, 'cog_item_id' => in_array($kind, ['backload', 'issue']) ? $line->id : null, 'kind' => $kind, 'unit' => 'PCS', 'quantity' => $quantity, 'balance_after' => $after, 'location' => 'LABUAN', 'reference' => $kind === 'receipt' ? 'PO-123' : null, 'note' => 'Test', 'request_key' => 'labuan-'.$i, 'user_id' => $user->id, 'created_at' => $date]);
        }
        $this->get(route('miri-reports.labuan-consumable', ['preview' => 1, 'month' => '2026-09']))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('MiriReports/LabuanConsumable')->where('filters.location', 'LBN')->has('report.rows', 2)
            ->where('report.rows.0.opening', 10)->where('report.rows.0.received', 7)->where('report.rows.0.issued', 2)
            ->where('report.rows.0.misc', -1)->where('report.rows.0.closing', 14)->where('report.rows.0.rack', 'RACK A')
            ->where('report.rows.0.source', "PO-123\nSUPPLIER YARD")->where('report.rows.0.destination', 'VESSEL A')
            ->where('report.rows.0.received_dates', "01/09/2026\n02/09/2026")->where('report.rows.1.received', null));
        $this->assertSame('14.000', $item->fresh()->stock_balance);
        $this->assertDatabaseCount('miri_construction_stock_movements', 6);
        $historical = app(LabuanConsumableInventoryReport::class)->generate($branch, '2026-07');
        $this->assertCount(0, $historical['rows']);
    }

    public function test_original_labuan_template_expands_without_losing_rows_or_signoff(): void
    {
        [, $branch] = $this->reader();
        foreach (range(1, 35) as $i) {
            MiriConstructionItem::create(['branch_id' => $branch, 'category' => $i % 2 ? 'CONSUMABLE' : 'PPE', 'description' => sprintf('Item %02d', $i), 'current_location' => 'LBN', 'unit' => 'PCS', 'stock_balance' => 1, 'storage_rack' => '=literal']);
        }
        $response = $this->get(route('miri-reports.labuan-consumable.export'))->assertOk()->assertDownload('labuan-warehouse-general-store-consumable-inventory-report-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $template = new ZipArchive;
        $template->open(resource_path('report-templates/labuan-consumable-inventory.xlsx'));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with($zip->getNameIndex($i), '.xml') || str_ends_with($zip->getNameIndex($i), '.rels')) {
                    $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
                }
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Item 35', $xml);
            $this->assertStringContainsString('LABUAN GENERAL STORES', $xml);
            $this->assertStringContainsString('=literal', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertSame(1, substr_count($xml, 'GRAND TOTAL'));
            $this->assertStringContainsString('I43:K43', $xml);
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertStringContainsString('$A$1:$S$45', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringNotContainsString('#REF!', $zip->getFromName('xl/workbook.xml'));
            $doc = simplexml_load_string($xml);
            $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertCount(35, $doc->xpath('//s:row[@r >= 7 and @r <= 41]/s:c[starts-with(@r,"C")]/s:is'));
            $this->assertSame('35', (string) $doc->xpath('//s:c[@r="N42"]/s:v')[0]);
            $this->assertSame('Name :', (string) $doc->xpath('//s:c[@r="B44"]/s:is/s:t')[0]);
        } finally {
            $zip->close();
            $template->close();
            unlink($path);
        }
    }

    public function test_validation_permissions_and_unavailable_schema(): void
    {
        [$user] = $this->reader();
        foreach (['miri-reports.labuan-consumable', 'miri-reports.labuan-consumable.export'] as $route) {
            $this->getJson(route($route, ['location' => 'BTU']))->assertUnprocessable();
            $this->getJson(route($route, ['month' => '2026-10']))->assertUnprocessable();
        }
        \Illuminate\Support\Facades\Schema::drop('miri_construction_stock_movements');
        $this->get(route('miri-reports.labuan-consumable', ['preview' => 1]))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('report.rows', 0)->where('report.unavailable', 'Labuan Consumable reporting requires the construction register and stock-history database migrations.'));
        $this->get(route('miri-reports.labuan-consumable.export'))->assertUnprocessable();
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['miri-reports.labuan-consumable', 'miri-reports.labuan-consumable.export'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
