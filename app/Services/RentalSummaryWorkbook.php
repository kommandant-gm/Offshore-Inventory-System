<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use ZipArchive;

class RentalSummaryWorkbook
{
    use WorkbookXml;

    public function create(array $report, array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rental-summary-');
        if ($path === false) {
            throw new RuntimeException('Unable to create report file.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (! copy(resource_path('report-templates/equipment-rental-summary.xlsx'), $path) || $zip->open($path) !== true) {
                throw new RuntimeException('Unable to open rental template.');
            }
            $opened = true;
            $doc = $this->document($zip->getFromName('xl/worksheets/sheet1.xml'));
            $xp = $this->xpath($doc);
            $body = $xp->query('//s:sheetData')->item(0);
            $original = [];
            foreach ($xp->query('s:row', $body) as $row) {
                $original[(int) $row->getAttribute('r')] = $row->cloneNode(true);
            }
            $offset = max(17, count($report['rows'])) - 17;
            while ($body->firstChild) {
                $body->removeChild($body->firstChild);
            }
            $rows = [];
            for ($n = 1; $n <= 26 + $offset; $n++) {
                $source = $n < 5 ? $n : ($n < 22 + $offset ? 5 : $n - $offset);
                $row = $original[$source]->cloneNode(true);
                $row->setAttribute('r', (string) $n);
                foreach ($xp->query('s:c', $row) as $cell) {
                    $cell->setAttribute('r', preg_replace('/\d+/', '', $cell->getAttribute('r')).$n);
                }
                $body->appendChild($row);
                $rows[$n] = $row;
            }
            $this->cell($rows[3], 'A', 'PROJECT / CONTRACT: '.($filters['project'] ?: 'ALL'));
            $this->cell($rows[3], 'G', 'WAREHOUSE / LOCATION: '.($filters['location'] ?: 'ALL'));
            $this->cell($rows[3], 'K', 'MONTH: '.strtoupper(CarbonImmutable::createFromFormat('!Y-m', $filters['month'])->format('F Y')));
            $columns = ['A', 'B', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
            foreach ($report['rows'] as $i => $data) {
                $lines = 1;
                foreach (array_keys(RentalSummaryReport::COLUMNS) as $index => $key) {
                    $this->cell($rows[5 + $i], $columns[$index], $data[$key]);
                    $textLines = 0;
                    foreach (explode("\n", (string) $data[$key]) as $line) {
                        $textLines += max(1, (int) ceil(mb_strwidth($line) / ($index === 1 ? 42 : 21)));
                    }
                    $lines = max($lines, $textLines);
                }
                $rows[5 + $i]->setAttribute('ht', (string) min(409, max(30, $lines * 14)));
                $rows[5 + $i]->setAttribute('customHeight', '1');
            }
            // Rebuild body merges and move the note/sign-off block below every exported item.
            $merges = $xp->query('//s:mergeCells')->item(0);
            $refs = [];
            foreach ($xp->query('s:mergeCell', $merges) as $merge) {
                $ref = $merge->getAttribute('ref');
                preg_match('/\d+/', $ref, $match);
                $row = (int) $match[0];
                if ($row >= 5 && $row <= 21) {
                    continue;
                }
                $refs[] = $row >= 22 ? preg_replace_callback('/\d+/', fn ($m) => (int) $m[0] + $offset, $ref) : $ref;
            }
            for ($n = 5; $n < 22 + $offset; $n++) {
                $refs[] = 'B'.$n.':C'.$n;
            }
            while ($merges->firstChild) {
                $merges->removeChild($merges->firstChild);
            }
            foreach ($refs as $ref) {
                $merge = $doc->createElementNS(self::NS, 'mergeCell');
                $merge->setAttribute('ref', $ref);
                $merges->appendChild($merge);
            }
            $merges->setAttribute('count', (string) count($refs));
            $xp->query('//s:dimension')->item(0)->setAttribute('ref', 'A1:L'.(26 + $offset));
            $zip->addFromString('xl/worksheets/sheet1.xml', $doc->saveXML());
            $styles = $this->document($zip->getFromName('xl/styles.xml'));
            foreach ($this->xpath($styles)->query('//s:cellXfs/s:xf') as $format) {
                $alignment = $this->xpath($styles)->query('s:alignment', $format)->item(0);
                if (! $alignment) {
                    $alignment = $styles->createElementNS(self::NS, 'alignment');
                    $format->appendChild($alignment);
                }
                $alignment->setAttribute('wrapText', '1');
                $format->setAttribute('applyAlignment', '1');
            }
            $zip->addFromString('xl/styles.xml', $styles->saveXML());
            $workbook = $this->document($zip->getFromName('xl/workbook.xml'));
            $wx = $this->xpath($workbook);
            $name = 'RENTAL '.substr($filters['month'], 0, 4);
            $wx->query('//s:sheet')->item(0)->setAttribute('name', $name);
            $wx->query('//s:definedName[@name="_xlnm.Print_Area"]')->item(0)->nodeValue = "'{$name}'!\$A\$1:\$L\$".(26 + $offset);
            $titles = $workbook->createElementNS(self::NS, 'definedName');
            $titles->setAttribute('name', '_xlnm.Print_Titles');
            $titles->setAttribute('localSheetId', '0');
            $titles->nodeValue = "'{$name}'!\$4:\$4";
            $wx->query('//s:definedNames')->item(0)->appendChild($titles);
            foreach (iterator_to_array($workbook->getElementsByTagNameNS('http://schemas.openxmlformats.org/markup-compatibility/2006', 'AlternateContent')) as $node) {
                $node->parentNode->removeChild($node);
            }
            $sheet = $workbook->createElementNS(self::NS, 'sheet');
            $sheet->setAttribute('name', 'Report notes');
            $sheet->setAttribute('sheetId', '2');
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
            foreach (['Report notes', ...$report['notes']] as $i => $note) {
                $row = $notes->createElementNS(self::NS, 'row');
                $row->setAttribute('r', (string) ($i + 1));
                $noteBody->appendChild($row);
                $this->cell($row, 'A', $note);
            }
            $zip->addFromString('xl/worksheets/report-notes.xml', $notes->saveXML());
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish rental report.');
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
