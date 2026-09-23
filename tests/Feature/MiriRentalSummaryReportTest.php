<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MiriRentalItem;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MiriRentalSummaryReportTest extends TestCase
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

    public function test_database_mapping_filters_dates_and_exclusions(): void
    {
        [, $branch] = $this->reader();
        $base = ['branch_id' => $branch, 'description' => 'Air compressor', 'category' => 'EQUIPMENT', 'project_contract' => 'SBA', 'current_location' => 'BTU', 'onhire_certificate_date' => '2026-08-01', 'serial_tag_equipment_no' => '00123'];
        $item = MiriRentalItem::create([...$base, 'do_date' => '2026-07-31', 'return_cog_date' => '2026-09-15', 'offhire_certificate_date' => '2026-09-14', 'po_or_sr_no' => 'SR001', 'do_no' => 'DO002']);
        foreach ([['description' => 'Gas cylinder'], ['description' => 'Oxygen cylinder'], ['onhire_certificate_date' => '2026-10-01'], ['return_cog_date' => '2026-08-31'], ['project_contract' => 'SKA'], ['current_location' => 'LBN'], ['branch_id' => Branch::where('code', 'KL-IT')->value('id')]] as $extra) {
            MiriRentalItem::create([...$base, ...$extra]);
        }
        $this->get(route('miri-reports.rental', ['preview' => 1, 'month' => '2026-09', 'project' => 'SBA', 'location' => 'BTU']))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('MiriReports/RentalSummary')->has('report.rows', 1)->where('report.rows.0.identifier', '00123')
            ->where('report.rows.0.documents', "SR/PO: SR001\nDO: DO002")
            ->where('report.rows.0.received', "Received: 31/07/2026\nOn-hire: 01/08/2026")
            ->where('report.rows.0.returned', "Return: 15/09/2026\nOff-hire: 14/09/2026"));
        $this->assertSame('00123', $item->fresh()->serial_tag_equipment_no);
    }

    public function test_export_preserves_template_and_expands_all_rows_and_footer(): void
    {
        [, $branch] = $this->reader();
        foreach (range(1, 20) as $i) {
            MiriRentalItem::create(['branch_id' => $branch, 'description' => sprintf('Equipment %02d', $i), 'serial_tag_equipment_no' => '000'.$i, 'remarks' => '=HYPERLINK("literal")']);
        }
        $response = $this->get(route('miri-reports.rental.export', ['month' => '2026-09']))->assertOk()->assertDownload('equipment-rental-list-summary-2026-09.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $template = new ZipArchive;
        $template->open(resource_path('report-templates/equipment-rental-summary.xlsx'));
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with($zip->getNameIndex($i), '.xml') || str_ends_with($zip->getNameIndex($i), '.rels')) {
                    $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
                }
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Equipment 20', $xml);
            $this->assertStringContainsString('=HYPERLINK', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringContainsString('B24:C24', $xml);
            $this->assertStringContainsString('A25:L25', $xml);
            $this->assertStringContainsString('$A$1:$L$29', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('DE-F-187A', $xml);
            $this->assertSame($template->getFromName('xl/media/image1.png'), $zip->getFromName('xl/media/image1.png'));
            $this->assertStringContainsString('hire start date missing', $zip->getFromName('xl/worksheets/report-notes.xml'));
            $doc = simplexml_load_string($xml);
            $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('0001', (string) $doc->xpath('//s:c[@r="D5"]/s:is/s:t')[0]);
            $this->assertCount(20, $doc->xpath('//s:row[@r >= 5 and @r <= 24]/s:c[starts-with(@r,"B")]/s:is'));
        } finally {
            $zip->close();
            $template->close();
            unlink($path);
        }
        $this->assertDatabaseCount('miri_rental_items', 20);
    }

    public function test_access_and_filter_validation(): void
    {
        [$user] = $this->reader();
        foreach (['miri-reports.rental', 'miri-reports.rental.export'] as $route) {
            $this->getJson(route($route, ['month' => '2026-10']))->assertUnprocessable();
            $this->getJson(route($route, ['month' => 'invalid']))->assertUnprocessable();
        }
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        foreach (['miri-reports.rental', 'miri-reports.rental.export'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
