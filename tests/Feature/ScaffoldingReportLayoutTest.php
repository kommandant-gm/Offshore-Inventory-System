<?php

namespace Tests\Feature;

use App\Services\{ScaffoldingReportLayout, ScaffoldingReportWorkbook};
use Tests\TestCase;

class ScaffoldingReportLayoutTest extends TestCase
{
    private function report(): array
    {
        return ['title' => 'Scaffolding materials', 'as_of' => '2026-10-27', 'company' => 'DESB', 'generated_at' => '2026-10-27', 'warnings' => [], 'notes' => [], 'unallocatedReturns' => [], 'groups' => [],
            'columns' => [['key' => 'tube', 'group' => 'TUBES', 'label' => 'TUBE 6 FT', 'unit' => 'PC'],
                ['key' => 'set', 'group' => 'TUBES', 'label' => 'TUBE 6 FT', 'unit' => 'SET'],
                ['key' => 'ambiguous', 'group' => 'LADDER', 'label' => 'LADDER 12 FT', 'unit' => 'PC']],
            'rows' => [
                ['kind' => 'project', 'label' => 'PROJECT A', 'contract' => 'SHELL', 'mr' => ['MR-123'], 'values' => ['tube' => 3.25, 'set' => 20, 'ambiguous' => 2]],
                ['kind' => 'yard', 'label' => 'LABUAN YARD', 'contract' => '', 'mr' => [], 'values' => ['tube' => 10, 'set' => 20, 'ambiguous' => 2]],
                ['kind' => 'yard', 'label' => 'BINTULU YARD', 'contract' => '', 'mr' => [], 'values' => ['tube' => null, 'set' => 20, 'ambiguous' => 2]],
            ]];
    }

    public function test_fixed_template_maps_only_unambiguous_piece_columns_and_propagates_unknown_totals(): void
    {
        $layout = app(ScaffoldingReportLayout::class)->generate($this->report());
        $this->assertCount(33, $layout['widths']); $this->assertCount(59, $layout['rows']);
        $this->assertCount(2, $layout['unmapped']);
        $value = fn ($row, $col) => collect($layout['rows'][$row - 1]['cells'])->firstWhere('col', $col)['value'];
        $this->assertSame(3.25, $value(7, 'C')); $this->assertSame(3.25, $value(53, 'C'));
        $this->assertEquals(10, $value(56, 'C')); $this->assertNull($value(57, 'C')); $this->assertNull($value(58, 'C'));
        $this->assertNull($value(7, 'D')); $this->assertSame('', $value(7, 'H'));
        $this->assertStringContainsString('27TH OCTOBER 2026', $value(1, 'A'));
        $this->assertSame('SCAFFOLDING TUBES', $value(3, 'B'));
    }

    public function test_additional_projects_extend_the_form_without_overwriting_totals(): void
    {
        $report = $this->report(); $project = $report['rows'][0];
        for ($i = 0; $i < 60; $i++) $report['rows'][] = [...$project, 'label' => 'PROJECT '.$i];
        $layout = app(ScaffoldingReportLayout::class)->generate($report);
        $this->assertGreaterThan(59, count($layout['rows']));
        $total = collect($layout['rows'])->firstWhere('source', 53);
        $this->assertSame(198.25, collect($total['cells'])->firstWhere('col', 'C')['value']);
        $this->assertSame(58, $layout['rows'][count($layout['rows']) - 2]['source']);
    }

    public function test_decimal_values_are_not_rounded_by_original_integer_cell_format(): void
    {
        $path = app(ScaffoldingReportWorkbook::class)->create($this->report()); $zip = new \ZipArchive;
        try {
            $zip->open($path); $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cell = $sheet->xpath('//s:c[@r="C7"]')[0]; $style = (int) $cell['s'];
            $this->assertSame(3.25, (float) $cell->v);
            $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
            $this->assertSame(0, (int) $styles->cellXfs->xf[$style]['numFmtId']);
        } finally { $zip->close(); unlink($path); }
    }
}
