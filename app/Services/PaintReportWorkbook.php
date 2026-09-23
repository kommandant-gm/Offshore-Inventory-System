<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** Small, values-only XLSX export: user content is always written as text, never formulas. */
class PaintReportWorkbook
{
    private function escape(mixed $value): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value);

        return htmlspecialchars(mb_substr($text ?? '', 0, 32767), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function row(int $number, array $values, int $style = 0): string
    {
        $xml = '<row r="'.$number.'">';
        foreach (array_values($values) as $index => $value) {
            $ref = chr(65 + $index).$number;
            if ($value === null) {
                $xml .= '<c r="'.$ref.'" s="0"/>';

                continue;
            }
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$ref.'" s="2"><v>'.$value.'</v></c>';
            } else {
                $xml .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    public function create(array $report, array $filters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'paint-report-');
        if ($path === false) {
            throw new RuntimeException('Unable to create report file.');
        }
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open report archive.');
            }
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="40" customWidth="1"/><col min="3" max="12" width="19" customWidth="1"/><col min="13" max="16" width="35" customWidth="1"/></cols><sheetData>';
            $sheet .= $this->row(1, ['DAYANG INVENTORY — MONTHLY PAINT INVENTORY'], 1);
            $sheet .= $this->row(2, ["Month: {$filters['month']} | Location: {$filters['location']} | Paint: {$filters['brand']}"], 1);
            $sheet .= $this->row(3, ['Blank = unavailable. See Report notes for coverage and movement definitions.']);
            $sheet .= $this->row(4, ['Generated: '.now('Asia/Kuala_Lumpur')->format('Y-m-d H:i').' MYT']);
            $sheet .= $this->row(5, array_values(PaintInventoryReport::COLUMNS), 1);
            foreach ($report['rows'] as $i => $row) {
                $sheet .= $this->row($i + 6, array_map(fn ($key) => $key === 'id' ? (string) $row[$key] : $row[$key], array_keys(PaintInventoryReport::COLUMNS)));
            }
            $last = max(5, count($report['rows']) + 5);
            $sheet .= '</sheetData><autoFilter ref="A5:P'.$last.'"/><mergeCells count="4"><mergeCell ref="A1:P1"/><mergeCell ref="A2:P2"/><mergeCell ref="A3:P3"/><mergeCell ref="A4:P4"/></mergeCells><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
            $notes = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'"><cols><col min="1" max="1" width="120" customWidth="1"/></cols><sheetData>'.$this->row(1, ['Report notes'], 1);
            foreach ($report['notes'] as $i => $note) {
                $notes .= $this->row($i + 2, [$note]);
            }
            $notes .= '</sheetData></worksheet>';
            $parts = [
                '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
                '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Paint inventory" sheetId="1" r:id="rId1"/><sheet name="Report notes" sheetId="2" r:id="rId2"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="'.$ns.'"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.000"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF276749"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
                'xl/worksheets/sheet1.xml' => $sheet,
                'xl/worksheets/sheet2.xml' => $notes,
            ];
            foreach ($parts as $name => $content) {
                if (! $zip->addFromString($name, $content)) {
                    throw new RuntimeException('Unable to write report.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish report.');
            }

            return $path;
        } catch (\Throwable $error) {
            if ($zip->status === ZipArchive::ER_OK) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }
            @unlink($path);
            throw $error;
        }
    }
}
