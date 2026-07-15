<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CategoryType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ItAssetImportService
{
    public function analyse(string $path): array
    {
        $rows = $this->rows($path); $valid = []; $warnings = []; $rejected = []; $seenTags = []; $seenSerials = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2; $mapped = $this->map($row);
            if ($mapped['asset_tag_no'] === '') { $rejected[] = "Row {$line}: asset tag is missing."; continue; }
            $tagKey = Str::lower($mapped['asset_tag_no']);
            if (isset($seenTags[$tagKey]) || Asset::withoutGlobalScopes()->where('asset_tag_no', $mapped['asset_tag_no'])->exists()) { $rejected[] = "Row {$line}: duplicate asset tag {$mapped['asset_tag_no']}."; continue; }
            $seenTags[$tagKey] = true;
            if ($mapped['serial_no']) {
                $serialKey = Str::lower($mapped['serial_no']);
                if (isset($seenSerials[$serialKey])) $warnings[] = "Row {$line}: duplicate serial {$mapped['serial_no']} in this file.";
                $seenSerials[$serialKey] = true;
            } else $warnings[] = "Row {$line}: serial number is blank.";
            if (! $mapped['purchase_year']) $warnings[] = "Row {$line}: purchase year is blank or invalid.";
            $valid[] = ['line' => $line, 'data' => $mapped];
        }
        return ['total' => count($rows), 'ready' => count($valid), 'warning_count' => count($warnings), 'rejected_count' => count($rejected), 'warnings' => array_slice($warnings, 0, 100), 'rejected' => array_slice($rejected, 0, 100), 'valid' => $valid];
    }

    public function import(string $path, int $userId): array
    {
        $report = $this->analyse($path);
        DB::transaction(function () use ($report, $userId) {
            $location = Location::query()->where('code', 'KL')->orWhere('name', 'like', '%KL%')->first();
            foreach ($report['valid'] as $entry) {
                $data = $entry['data'];
                $category = Category::firstOrCreate(['name' => $data['category_name']], ['code' => Str::upper(Str::slug($data['category_name'], '-')), 'type' => CategoryType::Asset->value, 'active' => true]);
                $asset = Asset::create([
                    'asset_tag_no' => $data['asset_tag_no'], 'description' => $data['model'] ?: $data['category_name'],
                    'category_id' => $category->id, 'model' => $data['model'], 'serial_no' => $data['serial_no'],
                    'operating_system' => $data['operating_system'], 'purchase_year' => $data['purchase_year'],
                    'year' => $data['purchase_year'], 'current_location_id' => $location?->id,
                    'current_status' => $data['current_status'], 'current_condition' => AssetCondition::Good->value,
                    'ownership' => 'Company', 'active' => $data['active'], 'remarks' => 'Migrated from legacy KL IT inventory.',
                ]);
                if ($data['assigned_to_name']) $asset->assignments()->create(['assigned_to_name' => $data['assigned_to_name'], 'department' => $data['department'], 'assigned_at' => now()->toDateString(), 'assigned_by' => $userId, 'remarks' => 'Initial assignment imported from legacy system.']);
            }
        });
        return collect($report)->except('valid')->all();
    }

    private function map(array $row): array
    {
        $get = fn (string $key) => trim((string) ($row[$key] ?? ''));
        $checkedOut = $get('checked out to'); $status = Str::upper($get('status'));
        return ['asset_tag_no'=>$get('asset tag'),'serial_no'=>$get('serial'),'model'=>$get('model'),'category_name'=>$get('category') ?: 'Uncategorised','assigned_to_name'=>$checkedOut,'department'=>$get('department'),'operating_system'=>$get('operating system'),'purchase_year'=>filter_var($get('year of purchase'), FILTER_VALIDATE_INT) ?: null,'current_status'=>match($status){'REPAIR','UNDER REPAIR'=>AssetStatus::UnderRepair->value,'DAMAGED'=>AssetStatus::Damaged->value,'DISPOSED','RETIRED'=>AssetStatus::Disposed->value,default=>$checkedOut ? AssetStatus::Deployed->value : AssetStatus::Available->value},'active'=>!in_array($status,['DISPOSED','RETIRED'],true)];
    }

    private function rows(string $path): array
    {
        return Str::lower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx' ? $this->xlsxRows($path) : $this->csvRows($path);
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb'); if (! $handle) throw new RuntimeException('Unable to read import file.');
        $firstLine=(string)fgets($handle); $delimiters=[","=>substr_count($firstLine,','),";"=>substr_count($firstLine,';'),"\t"=>substr_count($firstLine,"\t")]; arsort($delimiters); $delimiter=(string)array_key_first($delimiters); rewind($handle);
        $headers = array_map(fn ($v) => $this->header($v), fgetcsv($handle,0,$delimiter) ?: []); $rows=[];
        while (($values=fgetcsv($handle,0,$delimiter)) !== false) { $values=array_pad($values,count($headers),''); $rows[]=array_combine($headers,array_slice($values,0,count($headers))); }
        fclose($handle); return $rows;
    }

    private function xlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP Zip extension is required for XLSX imports.');
        $zip=new ZipArchive(); if ($zip->open($path)!==true) throw new RuntimeException('Unable to open XLSX file.');
        $shared=[]; $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) { $xml=simplexml_load_string($sharedXml); foreach ($xml?->xpath('//*[local-name()="si"]') ?: [] as $si) $shared[]=trim(implode('', array_map(fn ($node) => (string) $node, $si->xpath('.//*[local-name()="t"]') ?: []))); }
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); if (!$sheet) throw new RuntimeException('The first worksheet could not be read.');
        $xml=simplexml_load_string($sheet); $matrix=[];
        foreach ($xml->sheetData->row as $row) { $values=[]; foreach ($row->c as $cell) { $ref=(string)$cell['r']; preg_match('/^[A-Z]+/',$ref,$m); $col=$this->columnIndex($m[0]??'A'); $value=(string)$cell->v; if ((string)$cell['t']==='s') $value=$shared[(int)$value]??''; elseif ((string)$cell['t']==='inlineStr') $value=implode('',array_map(fn($node)=>(string)$node,$cell->xpath('.//*[local-name()="t"]')?:[])); $values[$col]=trim($value); } if ($values) { $max=max(array_keys($values)); $matrix[]=array_map(fn($i)=>$values[$i]??'',range(0,$max)); } }
        $headerIndex=collect($matrix)->search(fn($row)=>collect($row)->contains(fn($value)=>$this->header($value)==='asset tag'));
        if ($headerIndex===false) throw new RuntimeException('Could not find an "Asset Tag" header in the first worksheet.');
        $matrix=array_slice($matrix,(int)$headerIndex); $headers=array_map(fn($v)=>$this->header($v),array_shift($matrix)??[]); return array_map(function($values)use($headers){$values=array_pad($values,count($headers),'');return array_combine($headers,array_slice($values,0,count($headers)));},$matrix);
    }

    private function columnIndex(string $letters): int { $n=0; foreach(str_split($letters) as $c)$n=$n*26+(ord($c)-64); return $n-1; }
    private function header(mixed $value): string { return Str::lower(trim((string)preg_replace('/\s+/u',' ',str_replace(["\xEF\xBB\xBF","\xC2\xA0"],' ',(string)$value)))); }
}
