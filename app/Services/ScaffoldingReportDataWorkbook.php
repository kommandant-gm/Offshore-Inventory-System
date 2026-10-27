<?php

namespace App\Services;

use ZipArchive;
use RuntimeException;

class ScaffoldingReportDataWorkbook
{
    private function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    private function column(int $n): string
    {
        $result = '';
        while ($n > 0) { $n--; $result = chr(65 + $n % 26).$result; $n = intdiv($n, 26); }
        return $result;
    }
    private function cell(int $column, int $row, mixed $value, int $style = 0): string
    {
        $ref = $this->column($column).$row;
        if (is_float($value) || is_int($value)) return '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value === null ? '—' : (string) $value).'</t></is></c>';
    }
    public function create(array $report): string
    {
        $count = max(2, count($report['columns']) + 1); $last = $this->column($count);
        $merges = ['A1:'.$last.'1', 'A2:'.$last.'2'];
        $body = '<row r="1" ht="30" customHeight="1">'.$this->cell(1, 1, $report['title'].' — AS OF '.$report['as_of'], 1).'</row>';
        $body .= '<row r="2" ht="30" customHeight="1">'.$this->cell(1, 2, 'Company: '.$report['company'].' | Generated: '.$report['generated_at'].' | Recorded movements only; see notes below.').'</row>';
        $body .= '<row r="3" ht="30" customHeight="1">'.$this->cell(1, 3, 'PROJECTS & LOCATIONS', 1);
        $offset = 2;
        foreach ($report['groups'] as $group) {
            $body .= $this->cell($offset, 3, $group['label'], 1);
            if ($group['span'] > 1) $merges[] = $this->column($offset).'3:'.$this->column($offset + $group['span'] - 1).'3';
            $offset += $group['span'];
        }
        $body .= '</row><row r="4" ht="75" customHeight="1">'.$this->cell(1, 4, 'Contract / MR references', 1);
        foreach ($report['columns'] as $i => $column) $body .= $this->cell($i + 2, 4, $column['label']."\n".$column['unit'], 1);
        $body .= '</row>'; $rowNumber = 5;
        foreach ($report['rows'] as $row) {
            $label = $row['label'].($row['contract'] ? "\n".$row['contract'] : '').($row['mr'] ? "\nMR: ".implode(', ', $row['mr']) : '');
            $body .= '<row r="'.$rowNumber.'" ht="60" customHeight="1">'.$this->cell(1, $rowNumber, $label, $row['kind'] === 'yard' ? 1 : 0);
            foreach ($report['columns'] as $i => $column) $body .= $this->cell($i + 2, $rowNumber, $row['values'][$column['key']], 2);
            $body .= '</row>'; $rowNumber++;
        }
        $notes = [...$report['warnings'], ...$report['notes']];
        foreach ($report['unallocatedReturns'] as $return) $notes[] = 'Unallocated backload: '.$return['label'].' — '.$return['quantity'].' '.$return['unit'];
        foreach ($notes as $note) {
            $body .= '<row r="'.$rowNumber.'" ht="45" customHeight="1">'.$this->cell(1, $rowNumber, $note).'</row>';
            $merges[] = 'A'.$rowNumber.':'.$last.$rowNumber; $rowNumber++;
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.($rowNumber - 1).'"/><sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="4" topLeftCell="B5" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="48" customWidth="1"/><col min="2" max="'.$count.'" width="16" customWidth="1"/></cols><sheetData>'.$body.'</sheetData><mergeCells count="'.count($merges).'">'.implode('', array_map(fn ($ref) => '<mergeCell ref="'.$ref.'"/>', $merges)).'</mergeCells><pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'scaffold-report-');
        if ($path === false) throw new RuntimeException('Unable to create report file.');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to open report archive.');
            $files = [
                '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Scaffolding materials" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Scaffolding materials\'!$1:$4</definedName></definedNames></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2EFDA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
                'xl/worksheets/sheet1.xml' => $sheet,
            ];
            foreach ($files as $name => $content) if (! $zip->addFromString($name, $content)) throw new RuntimeException('Unable to write workbook.');
            if (! $zip->close()) throw new RuntimeException('Unable to finish workbook.');
            return $path;
        } catch (\Throwable $error) { @unlink($path); throw $error; }
    }
}
