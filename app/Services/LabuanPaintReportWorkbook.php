<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use ZipArchive;

class LabuanPaintReportWorkbook
{
    use WorkbookXml;

    public function create(array $report, array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'labuan-paint-');
        if ($path === false) {
            throw new RuntimeException('Unable to create report file.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (! copy(resource_path('report-templates/labuan-paint-inventory.xlsx'), $path) || $zip->open($path) !== true) {
                throw new RuntimeException('Unable to open Labuan paint template.');
            }
            $opened = true;
            $doc = $this->document($zip->getFromName('xl/worksheets/sheet1.xml'));
            $xp = $this->xpath($doc);
            $body = $xp->query('//s:sheetData')->item(0);
            $original = [];
            foreach ($xp->query('s:row', $body) as $row) {
                $original[(int) $row->getAttribute('r')] = $row->cloneNode(true);
            }
            $offset = max(0, count($report['rows']) - 38);
            while ($body->firstChild) {
                $body->removeChild($body->firstChild);
            }
            $rows = [];
            for ($n = 1; $n <= 69 + $offset; $n++) {
                $source = $n <= 45 ? $n : ($n < 46 + $offset ? 10 : $n - $offset);
                $row = $original[$source]->cloneNode(true);
                $row->setAttribute('r', (string) $n);
                foreach ($xp->query('s:c', $row) as $cell) {
                    $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                    $cell->setAttribute('r', $column.$n);
                    // Remove the source's hidden example stock records as well as body values.
                    if ($n >= 9 && $n <= 63 + $offset) {
                        $this->cell($row, $column, null);
                    }
                }
                $body->appendChild($row);
                $rows[$n] = $row;
            }
            $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month']);
            $this->cell($rows[3], 'E', 'PAINTS INVENTORY REPORT '.$month->format('Y'));
            // Excel date serial retains the original date cell's display format.
            $asOf = $month->endOfMonth()->min(CarbonImmutable::now('Asia/Kuala_Lumpur'))->startOfDay();
            $this->cell($rows[4], 'E', (int) CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')->diffInDays(CarbonImmutable::createFromFormat('!Y-m-d', $asOf->format('Y-m-d'), 'UTC')));
            $styles = $this->document($zip->getFromName('xl/styles.xml'));
            $formats = $this->xpath($styles)->query('//s:cellXfs')->item(0);
            $wrapped = [];
            $columns = ['A', 'C', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'];
            foreach ($report['rows'] as $i => $data) {
                $lines = 1;
                foreach (array_keys(LabuanPaintInventoryReport::COLUMNS) as $index => $key) {
                    $this->cell($rows[9 + $i], $columns[$index], $data[$key] ?? null);
                    $cell = $xp->query('s:c[@r="'.$columns[$index].(9 + $i).'"]', $rows[9 + $i])->item(0);
                    $base = (int) $cell->getAttribute('s');
                    if (! isset($wrapped[$base])) {
                        $format = $formats->childNodes->item($base)->cloneNode(true);
                        $alignment = $this->xpath($styles)->query('s:alignment', $format)->item(0);
                        if (! $alignment) {
                            $alignment = $styles->createElementNS(self::NS, 'alignment');
                            $format->appendChild($alignment);
                        }
                        $alignment->setAttribute('wrapText', '1');
                        $format->setAttribute('applyAlignment', '1');
                        $wrapped[$base] = $formats->childNodes->length;
                        $formats->appendChild($format);
                    }
                    $cell->setAttribute('s', (string) $wrapped[$base]);
                    $textLines = 0;
                    foreach (explode("\n", (string) ($data[$key] ?? '')) as $line) {
                        $textLines += max(1, (int) ceil(mb_strwidth($line) / ($key === 'description' ? 55 : 18)));
                    }
                    $lines = max($lines, $textLines);
                }
                $rows[9 + $i]->setAttribute('ht', (string) min(409, max((float) $rows[9 + $i]->getAttribute('ht'), $lines * 14)));
            }
            $merges = $xp->query('//s:mergeCells')->item(0);
            $formats->setAttribute('count', (string) $formats->childNodes->length);
            $zip->addFromString('xl/styles.xml', $styles->saveXML());
            foreach (iterator_to_array($xp->query('s:mergeCell', $merges)) as $merge) {
                if ($merge->getAttribute('ref') === 'C29:E29') {
                    $merges->removeChild($merge);
                }
            }
            for ($n = 9; $n <= 46 + $offset; $n++) {
                $merge = $doc->createElementNS(self::NS, 'mergeCell');
                $merge->setAttribute('ref', "C{$n}:E{$n}");
                $merges->appendChild($merge);
            }
            $merges->setAttribute('count', (string) $merges->childNodes->length);
            $xp->query('//s:dimension')->item(0)->setAttribute('ref', 'A1:AE'.(69 + $offset));
            $xp->query('//s:sheetView')->item(0)->setAttribute('showZeros', '1');
            $zip->addFromString('xl/worksheets/sheet1.xml', $doc->saveXML());

            $workbook = $this->document($zip->getFromName('xl/workbook.xml'));
            $wx = $this->xpath($workbook);
            $name = 'PAINT '.$month->format('Y');
            $wx->query('//s:sheet')->item(0)->setAttribute('name', $name);
            $wx->query('//s:definedName[@name="_xlnm.Print_Titles"]')->item(0)->nodeValue = "'{$name}'!\$6:\$8";
            $printArea = $workbook->createElementNS(self::NS, 'definedName');
            $printArea->setAttribute('name', '_xlnm.Print_Area');
            $printArea->setAttribute('localSheetId', '0');
            $printArea->nodeValue = "'{$name}'!\$A\$1:\$AB\$".(67 + $offset);
            $wx->query('//s:definedNames')->item(0)->appendChild($printArea);
            foreach (iterator_to_array($workbook->getElementsByTagNameNS('http://schemas.openxmlformats.org/markup-compatibility/2006', 'AlternateContent')) as $node) {
                $node->parentNode->removeChild($node);
            }
            $sheet = $workbook->createElementNS(self::NS, 'sheet');
            $sheet->setAttribute('name', 'Report notes');
            $sheet->setAttribute('sheetId', '3');
            $sheet->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'reportNotes');
            $wx->query('//s:sheets')->item(0)->appendChild($sheet);
            $zip->addFromString('xl/workbook.xml', $workbook->saveXML());
            foreach (['xl/_rels/workbook.xml.rels', '[Content_Types].xml'] as $part) {
                $meta = $this->document($zip->getFromName($part));
                $relationship = $part === 'xl/_rels/workbook.xml.rels';
                $entry = $meta->createElementNS($meta->documentElement->namespaceURI, $relationship ? 'Relationship' : 'Override');
                foreach ($relationship ? ['Id' => 'reportNotes', 'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet', 'Target' => 'worksheets/report-notes.xml'] : ['PartName' => '/xl/worksheets/report-notes.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'] as $key => $value) {
                    $entry->setAttribute($key, $value);
                }
                $meta->documentElement->appendChild($entry);
                $zip->addFromString($part, $meta->saveXML());
            }
            $notes = $this->document('<?xml version="1.0"?><worksheet xmlns="'.self::NS.'"><cols><col min="1" max="1" width="120" customWidth="1"/></cols><sheetData/></worksheet>');
            $noteBody = $this->xpath($notes)->query('//s:sheetData')->item(0);
            foreach (['Report month: '.$filters['month'], ...$report['notes']] as $i => $note) {
                $row = $notes->createElementNS(self::NS, 'row');
                $row->setAttribute('r', (string) ($i + 1));
                $noteBody->appendChild($row);
                $this->cell($row, 'A', $note);
            }
            $zip->addFromString('xl/worksheets/report-notes.xml', $notes->saveXML());
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish Labuan paint report.');
            }
            $opened = false;

            return $path;
        } catch (\Throwable $error) {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
            throw $error;
        }
    }
}
