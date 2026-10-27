<?php

namespace App\Services;

use ZipArchive;
use DOMXPath;

/** Read the generated workbook so screen values and Excel values stay identical. */
class LabuanPpeReportPreview
{
    use WorkbookXml;
    private function index(string $letters): int
    {
        return array_reduce(str_split($letters), fn ($value, $letter) => $value * 26 + ord($letter) - 64, 0);
    }
    public function create(array $report, array $filters): array
    {
        $path = app(LabuanPpeReportWorkbook::class)->create($report, $filters); $zip = new ZipArchive;
        try {
            if ($zip->open($path) !== true) throw new \RuntimeException('Unable to preview PPE workbook.');
            $doc = $this->document($zip->getFromName('xl/worksheets/sheet1.xml')); $xp = $this->xpath($doc);
            $widths = array_fill(0, 19, 52.5); $columnStyles = [];
            foreach ($xp->query('//s:cols/s:col') as $col) {
                for ($i = (int) $col->getAttribute('min'); $i <= min(19, (int) $col->getAttribute('max')); $i++) {
                    $widths[$i - 1] = $col->getAttribute('hidden') === '1' ? 0 : ((float) $col->getAttribute('width') * 7 + 5) * .75;
                    $columnStyles[$i] = (int) $col->getAttribute('style');
                }
            }
            $merges = [];
            foreach ($xp->query('//s:mergeCell') as $merge) $merges[] = $merge->getAttribute('ref');
            // Excel lets these titles overflow into empty cells; represent that visually in HTML.
            foreach (range(1, 4) as $row) $merges[] = 'C'.$row.':M'.$row;
            $spans = []; $covered = [];
            foreach ($merges as $merge) {
                preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $merge, $m);
                $start = $this->index($m[1]); $end = $this->index($m[3]);
                $spans[$m[2]][$start] = ['colspan' => $end - $start + 1, 'rowspan' => (int) $m[4] - (int) $m[2] + 1];
                for ($r = (int) $m[2]; $r <= (int) $m[4]; $r++) for ($c = $start; $c <= $end; $c++) {
                    if ($r !== (int) $m[2] || $c !== $start) $covered[$r][$c] = true;
                }
            }
            $rows = [];
            foreach ($xp->query('//s:sheetData/s:row') as $row) {
                $number = (int) $row->getAttribute('r'); $values = [];
                foreach ($xp->query('s:c', $row) as $cell) {
                    $index = $this->index(preg_replace('/\d/', '', $cell->getAttribute('r')));
                    $value = $cell->getAttribute('t') === 'inlineStr' ? $xp->evaluate('string(s:is/s:t)', $cell) : $xp->evaluate('string(s:v)', $cell);
                    $values[$index] = ['value' => $cell->getAttribute('t') !== 'inlineStr' && $value !== '' ? (float) $value : $value, 'style' => (int) $cell->getAttribute('s')];
                }
                $cells = [];
                for ($i = 1; $i <= 19; $i++) {
                    if (isset($covered[$number][$i]) || $widths[$i - 1] === 0) continue;
                    $cells[] = ['column' => $i, ...($values[$i] ?? ['value' => '', 'style' => $columnStyles[$i] ?? 0]), ...($spans[$number][$i] ?? ['colspan' => 1, 'rowspan' => 1])];
                }
                $rows[] = ['number' => $number, 'height' => (float) ($row->getAttribute('ht') ?: 13.5), 'cells' => $cells];
            }
            $drawing = $this->document($zip->getFromName('xl/drawings/drawing1.xml')); $dx = new DOMXPath($drawing);
            $dx->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $dx->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $offset = $dx->query('//xdr:pic/xdr:spPr/a:xfrm/a:off')->item(0); $extent = $dx->query('//xdr:pic/xdr:spPr/a:xfrm/a:ext')->item(0);
            $css = json_decode(file_get_contents(resource_path('report-templates/labuan-ppe-styles.json')), true, flags: JSON_THROW_ON_ERROR);
            $styles = $this->document($zip->getFromName('xl/styles.xml')); $formats = $this->xpath($styles)->query('//s:cellXfs/s:xf');
            $originalCount = count($css);
            for ($i = $originalCount; $i < $formats->length; $i++) {
                $format = $formats->item($i);
                for ($j = 0; $j < $originalCount; $j++) {
                    $base = $formats->item($j);
                    if (collect(['fontId', 'fillId', 'borderId', 'xfId'])->every(fn ($key) => $base->getAttribute($key) === $format->getAttribute($key))) { $css[$i] = $css[$j]; break; }
                }
                $css[$i] ??= $css[0];
            }
            return ['rows' => $rows, 'widths' => $widths, 'styles' => $css,
                'logo' => ['src' => 'data:image/png;base64,'.base64_encode($zip->getFromName('xl/media/image1.png')),
                    'left' => (float) $offset->getAttribute('x') / 12700, 'top' => (float) $offset->getAttribute('y') / 12700,
                    'width' => (float) $extent->getAttribute('cx') / 12700, 'height' => (float) $extent->getAttribute('cy') / 12700]];
        } finally { $zip->close(); if (is_file($path)) unlink($path); }
    }
}
