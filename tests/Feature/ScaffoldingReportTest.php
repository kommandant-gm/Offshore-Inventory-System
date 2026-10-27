<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Services\{ScaffoldingMaterialsReport, ScaffoldingReportWorkbook, ScaffoldingStockLedger};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScaffoldingReportTest extends TestCase
{
    use RefreshDatabase;
    private function item(array $extra = []): MajorEquipment
    {
        if (! auth()->check()) {
            $this->withoutVite();
            $user = User::factory()->create(['role' => 'miri', 'permissions' => AccessMatrix::permissionsForRole('miri')]);
            $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
            $this->actingAs($user);
        }
        return MajorEquipment::create(['branch_id' => Branch::where('code', 'MIRI')->value('id'), 'company' => 'DESB', 'section_1' => 'SCAFFOLDING SET', 'section_2' => 'TUBES', 'description' => 'TUBE', 'size_model' => '6 FT', 'unit' => 'PC', ...$extra]);
    }
    private function postMovement($item, $kind, $quantity, $date = '2026-10-01', $yard = 'BINTULU', array $extra = []): void
    {
        $ledger = app(ScaffoldingStockLedger::class);
        $data = ['kind' => $kind, 'yard' => $yard, 'quantity' => $quantity, 'document_date' => $date, 'note' => 'Verified',
            'request_key' => (string) Str::uuid(), 'stock_token' => $ledger->token(DB::table('scaffolding_stock_accounts')->where('equipment_id', $item->id)->where('yard', $yard)->first()),
            'reference' => (string) Str::uuid(), 'destination' => 'PROJECT A', 'contract' => 'SHELL', 'mr_reference' => 'MR-1', ...$extra];
        if ($kind === 'issue') $data['rows'] = [$data];
        $ledger->manual($item->id, $item->branch_id, $data, auth()->id());
    }
    private function report($date = '2026-10-06', $company = 'DESB'): array
    {
        return app(ScaffoldingMaterialsReport::class)->generate(Branch::where('code', 'MIRI')->value('id'), ['as_of' => $date, 'company' => $company]);
    }
    public function test_as_of_matrix_uses_confirmed_movements_and_does_not_treat_unknown_as_zero(): void
    {
        $item = $this->item(); $this->postMovement($item, 'opening', 100); $this->postMovement($item, 'opening', 0, yard: 'LABUAN');
        $this->postMovement($item, 'issue', 30, '2026-10-02'); $this->postMovement($item, 'issue', 10, '2026-10-10');
        $report = $this->report(); $key = $report['columns'][0]['key'];
        $this->assertSame(70, (int) $report['rows'][0]['values'][$key]);
        $this->assertSame(0, (int) $report['rows'][1]['values'][$key]);
        $this->assertSame(30, (int) $report['rows'][2]['values'][$key]);
        $this->assertSame(['MR-1'], $report['rows'][2]['mr']);
        $this->assertSame('SHELL', $report['rows'][2]['contract']);
        $this->assertNull($this->report('2026-09-30')['rows'][0]['values'][$key]);
        $this->postMovement($item, 'backload', 5, '2026-10-05');
        $report = $this->report();
        $this->assertSame(75, (int) $report['rows'][0]['values'][$key]);
        $this->assertNull($report['rows'][2]['values'][$key]);
        $this->assertSame(5, (int) $report['unallocatedReturns'][0]['quantity']);
        $this->assertNotEmpty($report['warnings']);
    }
    public function test_classification_merges_identical_materials_but_preserves_units_and_company_and_branch_scope(): void
    {
        $one = $this->item(); $two = $this->item(); $this->postMovement($one, 'opening', 10); $this->postMovement($two, 'opening', 20);
        $this->item(['unit' => 'SET']); $this->item(['company' => 'FTSB', 'description' => 'Other company']);
        $this->item(['branch_id' => Branch::where('code', '!=', 'MIRI')->value('id'), 'description' => 'Other branch']);
        $this->item(['section_1' => 'Machinery']);
        $report = $this->report();
        $this->assertCount(2, $report['columns']); $this->assertSame(2, $report['groups'][0]['span']);
        $key = $report['columns'][0]['key']; $this->assertSame('PC', $report['columns'][0]['unit']);
        $this->assertSame(30, (int) $report['rows'][0]['values'][$key]);
        $this->assertNull($report['rows'][1]['values'][$key]);
        $this->assertNull($report['rows'][0]['values'][$report['columns'][1]['key']]);
        $this->assertCount(3, $this->report(company: '')['columns']);
    }
    public function test_excel_is_valid_xml_with_merged_headers_and_literal_untrusted_text(): void
    {
        $item = $this->item(['description' => '=1+1 & <tube>']); $this->postMovement($item, 'opening', 12);
        $path = app(ScaffoldingReportWorkbook::class)->create($this->report());
        try {
            $zip = new \ZipArchive; $this->assertTrue($zip->open($path));
            for ($i = 0; $i < $zip->numFiles; $i++) { $xml = new \DOMDocument; $this->assertTrue($xml->loadXML($zip->getFromIndex($i))); }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('state="frozen"', $sheet); $this->assertStringContainsString('mergeCell', $sheet);
            $this->assertStringContainsString('=1+1 &amp; &lt;TUBE&gt;', $sheet); $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('Recorded-stock report', $sheet); $zip->close();
        } finally { unlink($path); }
    }
    public function test_preview_and_exports_share_access_and_filter_validation(): void
    {
        $this->item();
        $url = route('miri-reports.scaffolding');
        $this->get($url)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('MiriReports/Scaffolding')->has('report.columns', 1));
        $this->get($url.'?as_of=invalid')->assertSessionHasErrors('as_of');
        $this->get($url.'?company=OTHER')->assertSessionHasErrors('company');
        $this->get($url.'?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $response = $this->get($url.'?format=xlsx')->assertOk();
        $file = $response->baseResponse->getFile()->getPathname(); if (is_file($file)) unlink($file);
        $user = auth()->user(); $permissions = $user->permissions; $permissions['assets'] = 'none';
        $user->update(['permissions' => $permissions]); $this->actingAs($user->fresh());
        foreach (['', '?format=pdf', '?format=xlsx'] as $suffix) $this->get($url.$suffix)->assertForbidden();
    }
}
