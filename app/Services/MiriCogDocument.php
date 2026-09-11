<?php
namespace App\Services;

use App\Models\MiriCog;

class MiriCogDocument
{
    public function data(MiriCog $cog): array
    {
        $rows = []; $totals = [];
        $widths = ['description'=>44,'size_model'=>13,'identifier'=>19,'serial_no'=>21,'mr_reference'=>13,'remarks'=>29,'unit'=>9];
        foreach ($cog->items as $i => $item) {
            $unit = strtoupper(trim((string) $item->unit)) ?: 'UNSPECIFIED';
            $parts = explode('.', (string) $item->quantity);
            $milli = ((int) $parts[0])*1000 + (int) str_pad($parts[1] ?? '',3,'0');
            $totals[$unit] = ($totals[$unit] ?? 0) + $milli;
            $values = $item->toArray();
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
        if ($cog->remarks) {
            $rows[] = ['description'=>'DOCUMENT REMARKS:'];
            foreach (preg_split('/\\R/u', $cog->remarks) as $line) foreach (mb_str_split($line,44) ?: [''] as $part) $rows[] = ['description'=>$part];
        }
        $header = [];
        foreach (['consignee_name','consignee_department','from_department','from_location','to_location','copy_to','destination','issued_by_name','issued_designation','verified_by_name','verified_designation','receiver_name','receiver_designation'] as $key) {
            $value = (string) $cog->$key;
            $limit = 42;
            $header[$key] = mb_strlen($value) > $limit ? mb_substr($value,0,$limit).' *' : $value;
            if (mb_strlen($value) > $limit) {
                $rows[] = ['description'=>'* '.strtoupper(str_replace('_',' ',$key)).':'];
                foreach (preg_split('/\\R/u',$value) as $line) foreach (mb_str_split($line,44) ?: [''] as $part) $rows[] = ['description'=>$part];
            }
        }
        $totals = collect($totals)->map(fn ($value,$unit) => rtrim(rtrim(number_format($value/1000,3,'.',''),'0'),'.').' '.$unit)->values()->all();
        if (count($totals)>4) {
            $rows[] = ['description'=>'TOTAL QUANTITY BY UNIT:'];
            foreach ($totals as $total) foreach (mb_str_split($total,44) as $part) $rows[] = ['description'=>$part];
        }
        $pages = array_chunk($rows ?: [[]],16);
        foreach ($pages as &$page) while (count($page)<16) $page[] = [];
        unset($page);
        return ['pages'=>$pages,'totals'=>$totals,'header'=>$header];
    }
}
