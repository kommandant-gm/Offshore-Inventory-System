<?php

namespace App\Services;

use ZipArchive;
use RuntimeException;

class ScaffoldingReportWorkbook
{
    use WorkbookXml;

    public function create(array $report): string
    {
        $layout = app(ScaffoldingReportLayout::class)->generate($report);
        $report['notes'][] = 'The first sheet follows the supplied Scaffold Inventory Report design. Values are recorded quantities, not certified physical totals. See this detail sheet for data gaps and materials outside the fixed template columns.';
        foreach ($layout['unmapped'] as $column) $report['warnings'][] = 'Outside template columns: '.$column['group'].' / '.$column['label'].' ('.$column['unit'].'). Included in this detail sheet only.';
        $path = app(ScaffoldingReportDataWorkbook::class)->create($report);
        $template = new ZipArchive; $zip = new ZipArchive;
        try {
            if ($template->open(resource_path('report-templates/scaffolding-inventory.xlsx')) !== true || $zip->open($path) !== true) throw new RuntimeException('Unable to open scaffolding template.');
            $doc = $this->document($template->getFromName('xl/worksheets/sheet1.xml')); $xp = $this->xpath($doc);
            $styles = $this->document($template->getFromName('xl/styles.xml'));
            $formats = $this->xpath($styles)->query('//s:cellXfs')->item(0); $decimalStyles = [];
            $body = $xp->query('//s:sheetData')->item(0); $original = [];
            foreach ($xp->query('s:row', $body) as $row) $original[(int) $row->getAttribute('r')] = $row->cloneNode(true);
            while ($body->firstChild) $body->removeChild($body->firstChild);
            foreach ($layout['rows'] as $data) {
                $row = $original[$data['source']]->cloneNode(true); $row->setAttribute('r', (string) $data['number']);
                $row->setAttribute('ht', (string) $data['height']); $row->setAttribute('customHeight', '1');
                foreach ($xp->query('s:c', $row) as $cell) $cell->setAttribute('r', preg_replace('/\d+/', '', $cell->getAttribute('r')).$data['number']);
                foreach ($data['cells'] as $cell) {
                    $this->cell($row, $cell['col'], $cell['value'] === null ? '—' : $cell['value']);
                    if (is_float($cell['value']) && floor($cell['value']) !== $cell['value']) {
                        if (! isset($decimalStyles[$cell['style']])) {
                            $format = $formats->childNodes->item($cell['style'])->cloneNode(true); $format->setAttribute('numFmtId', '0');
                            $decimalStyles[$cell['style']] = $formats->childNodes->length; $formats->appendChild($format);
                            $formats->setAttribute('count', (string) $formats->childNodes->length);
                        }
                        $xp->query('s:c[@r="'.$cell['col'].$data['number'].'"]', $row)->item(0)->setAttribute('s', (string) $decimalStyles[$cell['style']]);
                    }
                }
                $body->appendChild($row);
            }
            $last = count($layout['rows']); $xp->query('//s:dimension')->item(0)->setAttribute('ref', 'A1:AG'.$last);
            foreach ($xp->query('//s:rowBreaks') as $breaks) $breaks->parentNode->removeChild($breaks);
            $details = $this->document($zip->getFromName('xl/worksheets/sheet1.xml'));
            foreach ($this->xpath($details)->query('//s:c') as $cell) $cell->setAttribute('s', '0');
            $zip->addFromString('xl/worksheets/sheet2.xml', $details->saveXML());
            $zip->addFromString('xl/worksheets/sheet1.xml', $doc->saveXML());
            $zip->addFromString('xl/styles.xml', $decimalStyles ? $styles->saveXML() : $template->getFromName('xl/styles.xml'));
            $zip->addFromString('xl/theme/theme1.xml', $template->getFromName('xl/theme/theme1.xml'));
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Scaffold Inventory Report" sheetId="1" r:id="rId1"/><sheet name="Report data and notes" sheetId="2" r:id="rId3"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Scaffold Inventory Report\'!$A$1:$AG$'.$last.'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Scaffold Inventory Report\'!$3:$5</definedName></definedNames></workbook>');
            $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $rels = str_replace('</Relationships>', '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/></Relationships>', $rels);
            $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
            $types = $zip->getFromName('[Content_Types].xml');
            $types = str_replace('</Types>', '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/></Types>', $types);
            $zip->addFromString('[Content_Types].xml', $types);
            $template->close(); if (! $zip->close()) throw new RuntimeException('Unable to save report.');
            return $path;
        } catch (\Throwable $error) { @unlink($path); throw $error; }
    }
}
