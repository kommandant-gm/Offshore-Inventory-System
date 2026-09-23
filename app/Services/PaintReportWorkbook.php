<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** Populate the supplied workbook, retaining its styles, artwork and print layout. */
class PaintReportWorkbook
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private function document(string $xml): DOMDocument
    {
        $doc = new DOMDocument;
        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid report template XML.');
        }

        return $doc;
    }

    private function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('s', self::NS);

        return $xp;
    }

    private function cell(DOMElement $row, string $column, mixed $value): void
    {
        $doc = $row->ownerDocument;
        $ref = $column.$row->getAttribute('r');
        $xp = $this->xpath($doc);
        $cell = $xp->query('s:c[@r="'.$ref.'"]', $row)->item(0);
        if (! $cell) {
            $cell = $doc->createElementNS(self::NS, 'c');
            $cell->setAttribute('r', $ref);
            $row->appendChild($cell);
        }
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if ($value === null || $value === '') {
            return;
        }
        if (is_int($value) || is_float($value)) {
            $cell->appendChild($doc->createElementNS(self::NS, 'v', (string) $value));

            return;
        }
        // Inline strings prevent formula injection and preserve literal references.
        $cell->setAttribute('t', 'inlineStr');
        $inline = $doc->createElementNS(self::NS, 'is');
        $text = $doc->createElementNS(self::NS, 't');
        $text->setAttribute('xml:space', 'preserve');
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value);
        $text->appendChild($doc->createTextNode(mb_substr($value ?? '', 0, 32767)));
        $inline->appendChild($text);
        $cell->appendChild($inline);
    }

    public function create(array $report, array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'paint-report-');
        if ($path === false) {
            throw new RuntimeException('Unable to create report file.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (! copy(resource_path('report-templates/paint-inventory.xlsx'), $path)) {
                throw new RuntimeException('Unable to copy report template.');
            }
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to open report template.');
            }
            $opened = true;
            $doc = $this->document($zip->getFromName('xl/worksheets/sheet1.xml'));
            $xp = $this->xpath($doc);
            $sheetData = $xp->query('//s:sheetData')->item(0);
            $original = [];
            foreach ($xp->query('s:row', $sheetData) as $row) {
                $original[(int) $row->getAttribute('r')] = $row->cloneNode(true);
            }
            $count = max(18, count($report['rows']));
            $offset = $count - 18;
            while ($sheetData->firstChild) {
                $sheetData->removeChild($sheetData->firstChild);
            }
            $rows = [];
            // The template body is rows 15?32. Extend it and move totals/sign-off below it.
            for ($n = 1; $n <= 40 + $offset; $n++) {
                $source = $n < 15 ? $n : ($n < 33 + $offset ? 15 : $n - $offset);
                $row = $original[$source]->cloneNode(true);
                $row->setAttribute('r', (string) $n);
                foreach ($xp->query('s:c', $row) as $cell) {
                    $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                    $cell->setAttribute('r', $column.$n);
                    // Discard old totals, old quantities and embedded signature names/dates.
                    if ($n >= 8) {
                        $this->cell($row, $column, null);
                    }
                }
                $sheetData->appendChild($row);
                $rows[$n] = $row;
            }
            $month = strtoupper(CarbonImmutable::createFromFormat('!Y-m', $filters['month'])->format('F Y'));
            $location = match ($filters['location']) {
                'BTU' => 'BINTULU YARD', 'LBN' => 'LABUAN YARD', default => 'BINTULU & LABUAN YARDS'
            };
            $this->cell($rows[2], 'B', '                     LOCATION : '.$location);
            $this->cell($rows[3], 'B', '                     MONTHLY : GENERAL STORE PAINT INVENTORY STATUS FOR '.$month);
            $this->cell($rows[4], 'B', '                     UPDATED: '.strtoupper(now('Asia/Kuala_Lumpur')->format('d F Y')));
            foreach ($report['rows'] as $i => $data) {
                foreach (array_keys(PaintInventoryReport::COLUMNS) as $index => $key) {
                    $this->cell($rows[15 + $i], chr(65 + $index), $data[$key] ?? null);
                }
            }
            $this->cell($rows[8], 'B', 'GRAND TOTAL');
            $this->cell($rows[33 + $offset], 'B', 'GRAND TOTAL FOR PAINT ITEM');
            foreach (['J' => 'opening_value', 'M' => 'closing_value'] as $column => $key) {
                $values = array_column(array_values(array_filter($report['rows'], fn ($row) => $row['unit'] === 'LTR')), $key);
                if ($values === [] || in_array(null, $values, true)) {
                    continue;
                }
                $total = array_sum(array_map(fn ($value) => (int) round($value * 100), $values)) / 100;
                $this->cell($rows[8], $column, $total);
                $this->cell($rows[33 + $offset], $column, $total);
            }
            // A single quantity total is meaningful only for one unit with complete coverage.
            if (count(array_unique(array_column($report['rows'], 'unit'))) === 1) {
                foreach (['D' => 'opening', 'E' => 'received', 'F' => 'issued', 'G' => 'closing'] as $column => $key) {
                    $values = array_column($report['rows'], $key);
                    if (in_array(null, $values, true)) {
                        continue;
                    }
                    $total = array_sum(array_map(fn ($value) => (int) round($value * 1000), $values)) / 1000;
                    $this->cell($rows[8], $column, $total);
                    $this->cell($rows[33 + $offset], $column, $total);
                }
            }
            foreach (['B' => 'PREPARED BY :...............................', 'H' => 'REVIEWED:...............................', 'N' => 'APPROVED BY:...............................'] as $column => $label) {
                $this->cell($rows[37 + $offset], $column, $label);
            }
            $xp->query('//s:dimension')->item(0)->setAttribute('ref', 'A1:Q'.(40 + $offset));
            // Actual zero balances must remain visible even though the blank source hides zeros.
            $xp->query('//s:sheetView')->item(0)->setAttribute('showZeros', '1');
            $zip->addFromString('xl/worksheets/sheet1.xml', $doc->saveXML());

            $workbook = $this->document($zip->getFromName('xl/workbook.xml'));
            $wx = $this->xpath($workbook);
            $name = 'PAINT '.substr($filters['month'], 0, 4);
            $wx->query('//s:sheet')->item(0)->setAttribute('name', $name);
            foreach ($wx->query('//s:definedName') as $defined) {
                $defined->nodeValue = $defined->getAttribute('name') === '_xlnm.Print_Area'
                    ? "'{$name}'!\$A\$1:\$Q\$".(40 + $offset) : "'{$name}'!\$6:\$8";
            }
            // Drop the source file's local folder path and stale calculation chain.
            foreach (iterator_to_array($workbook->getElementsByTagNameNS('http://schemas.openxmlformats.org/markup-compatibility/2006', 'AlternateContent')) as $node) {
                $node->parentNode->removeChild($node);
            }
            $notesSheet = $workbook->createElementNS(self::NS, 'sheet');
            $notesSheet->setAttribute('name', 'Report notes');
            $notesSheet->setAttribute('sheetId', '331');
            $notesSheet->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'reportNotes');
            $wx->query('//s:sheets')->item(0)->appendChild($notesSheet);
            $zip->addFromString('xl/workbook.xml', $workbook->saveXML());
            $zip->deleteName('xl/calcChain.xml');
            foreach (['xl/_rels/workbook.xml.rels', '[Content_Types].xml'] as $part) {
                $meta = $this->document($zip->getFromName($part));
                foreach (iterator_to_array($meta->documentElement->childNodes) as $node) {
                    if ($node instanceof DOMElement && (str_contains($node->getAttribute('Type'), 'calcChain') || str_contains($node->getAttribute('PartName'), 'calcChain'))) {
                        $node->parentNode->removeChild($node);
                    }
                }
                $relationship = $part === 'xl/_rels/workbook.xml.rels';
                $entry = $meta->createElementNS($meta->documentElement->namespaceURI, $relationship ? 'Relationship' : 'Override');
                foreach ($relationship ? ['Id' => 'reportNotes', 'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet', 'Target' => 'worksheets/report-notes.xml'] : ['PartName' => '/xl/worksheets/report-notes.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'] as $key => $value) {
                    $entry->setAttribute($key, $value);
                }
                $meta->documentElement->appendChild($entry);
                $zip->addFromString($part, $meta->saveXML());
            }
            $notes = $this->document('<?xml version="1.0"?><worksheet xmlns="'.self::NS.'"><cols><col min="1" max="1" width="120" customWidth="1"/></cols><sheetData/></worksheet>');
            $body = $this->xpath($notes)->query('//s:sheetData')->item(0);
            foreach (['Report notes', ...$report['notes']] as $i => $note) {
                $row = $notes->createElementNS(self::NS, 'row');
                $row->setAttribute('r', (string) ($i + 1));
                $body->appendChild($row);
                $this->cell($row, 'A', $note);
            }
            $zip->addFromString('xl/worksheets/report-notes.xml', $notes->saveXML());
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish report.');
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
