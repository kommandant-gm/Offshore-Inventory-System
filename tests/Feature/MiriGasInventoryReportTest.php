<?php

namespace Tests\Feature;

use App\Models\{Branch, MiriRentalItem, MiriCog, MiriCogItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class MiriGasInventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): int
    {
        $this->withoutVite();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-30 12:00:00', 'Asia/Kuala_Lumpur'));
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }

    public function test_all_locations_match_gas_register_and_monthly_confirmed_movements(): void
    {
        $branch = $this->reader();
        $item = MiriRentalItem::create(['branch_id' => $branch, 'supplier' => 'BIG', 'description' => 'A Oxygen', 'current_location' => 'BTU', 'unit' => 'CYL', 'po_or_sr_no' => 'PO-1']);
        MiriRentalItem::create(['branch_id' => $branch, 'supplier' => ' lgm ', 'description' => 'B Nitrogen', 'current_location' => 'LBN', 'issue_out_cog_no' => 'LEGACY', 'issue_out_cog_date' => '2026-10-02']);
        MiriRentalItem::create(['branch_id' => $branch, 'supplier' => 'OTHER', 'description' => 'EXCLUDE RENTAL']);
        MiriRentalItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'supplier' => 'BIG', 'description' => 'EXCLUDE BRANCH']);
        foreach ([
            ['draft', 'Issue out', '2026-10-01', 99, 'DRAFT'], ['cancelled', 'Issue out', '2026-10-02', 99, 'CANCELLED'],
            ['signed', 'Issue out', '2026-09-30', 99, 'PREVIOUS'], ['confirmed', 'Issue out', '2026-11-01', 99, 'NEXT'],
            ['signed', 'Issue out', '2026-10-03', 3, 'LOAD-1'], ['confirmed', 'Issue out', '2026-10-04', 2, 'LOAD-2'],
            ['confirmed', 'Received backload', '2026-10-05', 1, 'BACK-1'], ['confirmed', 'Return to supplier', '2026-10-06', 1, 'RETURN-1'],
        ] as [$status, $type, $date, $quantity, $ref]) {
            $cog = MiriCog::create(['branch_id' => $branch, 'cog_no' => $ref, 'status' => $status, 'movement_type' => $type,
                'document_date' => $date, 'from_location' => 'VESSEL A', 'to_location' => 'VESSEL B', 'destination' => $type === 'Return to supplier' ? 'BIG' : 'DESTINATION']);
            MiriCogItem::create(['branch_id' => $branch, 'miri_cog_id' => $cog->id, 'item_type' => 'Rental', 'item_id' => $item->id, 'quantity' => $quantity, 'unit' => 'CYL']);
        }
        $this->get(route('miri-reports.gas', ['preview' => 1]))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('MiriReports/Gas')->where('filters.month', '2026-10')->has('report.rows', 2)
            ->where('report.rows.0.load_cog', 'LOAD-1')->where('report.rows.0.load_qty', 3)->where('report.rows.0.load_unit', 'CYL')
            ->where('report.rows.0.load_location', 'DESTINATION')->where('report.rows.0.back_cog', 'BACK-1')->where('report.rows.0.back_qty', 1)
            ->where('report.rows.0.return_supplier', 'BIG')->where('report.rows.0.return_qty', '1 CYL')
            ->where('report.rows.0.remarks', fn ($text) => str_contains($text, 'Additional Issue out entries: 1'))
            ->where('report.rows.1.load_cog', 'LEGACY')->where('report.rows.1.load_qty', null));
        $item->update(['issue_out_cog_no' => 'DRAFT', 'issue_out_cog_date' => '2026-10-01']);
        $this->get(route('miri-reports.gas', ['preview' => 1, 'month' => '2026-08']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('report.rows.0.load_cog', null));
        $this->assertDatabaseCount('miri_cogs', 8);
    }

    public function test_excel_uses_paint_layout_with_gas_headers_and_all_rows(): void
    {
        $branch = $this->reader();
        foreach (range(1, 40) as $i) MiriRentalItem::create(['branch_id' => $branch, 'supplier' => 'BIG', 'description' => sprintf('Gas %02d', $i), 'current_location' => 'BTU', 'po_or_sr_no' => '=literal']);
        $response = $this->get(route('miri-reports.gas.export'))->assertOk()->assertDownload('gas-inventory-report-2026-10.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive; $zip->open($path);
        $template = new ZipArchive; $template->open(resource_path('report-templates/labuan-paint-inventory.xlsx'));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) if (str_ends_with($zip->getNameIndex($i), '.xml') || str_ends_with($zip->getNameIndex($i), '.rels')) $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('GAS INVENTORY REPORT 2026', $xml);
            $this->assertStringContainsString('LOCATIONS: ALL', $xml);
            $this->assertStringContainsString('Gas 40', $xml);
            $this->assertStringContainsString('=literal', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $doc = simplexml_load_string($xml); $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('Gas 40', (string) $doc->xpath('//s:c[@r="C48"]/s:is/s:t')[0]);
            $this->assertSame('Supplier', (string) $doc->xpath('//s:c[@r="J6"]/s:is/s:t')[0]);
            $this->assertSame('BIG', (string) $doc->xpath('//s:c[@r="J9"]/s:is/s:t')[0]);
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertStringContainsString('GAS 2026', $zip->getFromName('xl/workbook.xml'));
        } finally { $zip->close(); $template->close(); unlink($path); }
    }

    public function test_report_permissions_month_validation_and_missing_tables(): void
    {
        $this->reader();
        foreach (['miri-reports.gas', 'miri-reports.gas.export'] as $route) $this->getJson(route($route, ['month' => '2026-11']))->assertUnprocessable();
        \Illuminate\Support\Facades\Schema::drop('miri_cog_items');
        $this->get(route('miri-reports.gas', ['preview' => 1]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('report.rows', 0)->where('report.unavailable', 'Gas reporting requires the gas register and Internal Issue Note database tables.'));
        $this->get(route('miri-reports.gas.export'))->assertUnprocessable();
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['miri-reports.gas', 'miri-reports.gas.export'] as $route) $this->get(route($route))->assertForbidden();
    }
}
