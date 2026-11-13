<?php
namespace App\Services;

use App\Models\MiriCog;

class MiriCogDocument
{
    public function data(MiriCog $cog): array
    {
        $rows = []; $totals = [];
        $widths = ['description'=>33,'size_model'=>13,'identifier'=>21,'serial_no'=>13,'mr_reference'=>12,'remarks'=>12,'unit'=>5,'quantity'=>5];
        foreach ($cog->items as $i => $item) {
            $unit = strtoupper(trim((string) $item->unit)) ?: 'UNSPECIFIED';
            $parts = explode('.', (string) $item->quantity);
            $milli = ((int) $parts[0])*1000 + (int) str_pad($parts[1] ?? '',3,'0');
            $totals[$unit] = ($totals[$unit] ?? 0) + $milli;
            $values = $item->toArray();
            $values['quantity'] = rtrim(rtrim(number_format($milli/1000,3,'.',''),'0'),'.');
            if ($item->batch_no) $values['description'] = ($values['description'] ?? '')."\nBatch: ".$item->batch_no;
            $lines = [];
            foreach ($widths as $key => $width) {
                $lines[$key] = [];
                foreach (preg_split('/\R/u', (string) ($values[$key] ?? '')) as $line) {
                    array_push($lines[$key], ...(mb_str_split($line, $width) ?: ['']));
                }
            }
            $height = max(array_map('count',$lines));
            for ($j=0;$j<$height;$j++) {
                $row = ['item' => $j === 0 ? $i+1 : '', 'quantity' => $j === 0 ? rtrim(rtrim(number_format($milli/1000,3,'.',''),'0'),'.') : '', 'unit' => $j === 0 ? ($item->unit ?: '') : ''];
                foreach ($lines as $key=>$line) $row[$key] = $line[$j] ?? '';
                $rows[] = $row;
            }
        }
        $remarkLines = [];
        foreach (preg_split('/\\R/u', (string) $cog->remarks) as $line) {
            array_push($remarkLines, ...(mb_str_split($line, 105) ?: ['']));
        }
        $remarks = array_chunk($remarkLines, 3);
        $header = [];
        foreach (['consignee_name','consignee_department','from_department','from_location','to_location','copy_to','destination','issued_by_name','issued_designation','verified_by_name','verified_designation','receiver_name','receiver_designation'] as $key) {
            $value = (string) $cog->$key;
            $header[$key] = $value;
        }
        $totals = collect($totals)->map(fn ($value,$unit) => rtrim(rtrim(number_format($value/1000,3,'.',''),'0'),'.').' '.$unit)->values()->all();
        if (count($totals)>4) {
            $rows[] = ['description'=>'TOTAL QUANTITY BY UNIT:'];
            foreach ($totals as $total) foreach (mb_str_split($total,$widths['description']) as $part) $rows[] = ['description'=>$part];
        }
        // Continue very long metadata in the same field on subsequent sheets.
        // It must never become an equipment description or shrink to unreadable text.
        $headerChunks = [];
        foreach ($header as $key => $value) {
            if ($key === 'consignee_name' && $value === '') $value = $header['receiver_name'];
            if ($key === 'destination' && $value === '') $value = $header['to_location'];
            $width = match ($key) {
                'consignee_name', 'consignee_department', 'from_department', 'from_location' => 27,
                'to_location', 'copy_to' => 17,
                'destination' => 20,
                default => 20,
            };
            $lines = [];
            foreach (preg_split('/\\R/u', $value) as $line) {
                array_push($lines, ...(mb_str_split($line, $width) ?: ['']));
            }
            $headerChunks[$key] = array_map(fn ($lines) => implode("\n", $lines), array_chunk($lines, 2));
        }
        $metadataPages = max(array_map('count', $headerChunks));
        $wrappedMetadata = collect($headerChunks)->flatten()->contains(fn ($value) => str_contains($value, "\n"));
        $rowsPerPage = $metadataPages > 1 ? 1 : ($wrappedMetadata ? 2 : 8);
        $pages = array_chunk($rows ?: [[]], $rowsPerPage);
        $pageCount = max(count($pages), count($remarks), $metadataPages);
        while (count($pages) < $pageCount) $pages[] = [];
        foreach ($pages as &$page) while (count($page)<$rowsPerPage) $page[] = [];
        unset($page);
        $pageHeaders = [];
        foreach ($pages as $index => $_) {
            $pageHeaders[] = collect($headerChunks)->map(function ($chunks) use ($index) {
                if (count($chunks) === 1) return $chunks[0];
                return $chunks[$index] ?? '';
            })->all();
        }
        return ['pages'=>$pages,'totals'=>$totals,'header'=>$header,'pageHeaders'=>$pageHeaders,'metadataPages'=>$metadataPages,'remarks'=>$remarks];
    }
}
