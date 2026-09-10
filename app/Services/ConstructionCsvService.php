<?php

namespace App\Services;

use App\Models\MiriConstructionItem;
use App\Support\ConstructionFields;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ConstructionCsvService
{
    private function cleanHeader(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim(str_replace("\xEF\xBB\xBF", '', (string) $value))));
    }

    public function rows(UploadedFile $file): \Generator
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) throw ValidationException::withMessages(['file' => 'Unable to read CSV.']);
        try {
            $top = fgetcsv($handle, 0, ',', '"', '');
            $header = fgetcsv($handle, 0, ',', '"', '');
            $expected = [0 => 'CATEGORY & SECTION', 10 => 'CERTIFICATE', 12 => 'ISSUE OUT TO LOCATION', 15 => 'ISSUE OUT TO PERSONNEL',
                19 => 'CERTIFICATION', 21 => 'MR REQUEST', 22 => 'PURCHASE ORDER', 24 => 'RECEIVE (STOCK-IN)', 27 => 'RECEIVED BACKLOAD', 31 => 'UNFIT & WRITE-OFF'];
            if (! is_array($top) || ! is_array($header)) throw ValidationException::withMessages(['file' => 'Both grouped header rows are required.']);
            for ($i = 0; $i < 35; $i++) {
                if ($this->cleanHeader($top[$i] ?? null) !== ($expected[$i] ?? '') ||
                    $this->cleanHeader($header[$i] ?? null) !== $this->cleanHeader(ConstructionFields::FIELDS[$i]['label'])) {
                    throw ValidationException::withMessages(['file' => 'Column '.($i + 1).' does not match the Construction template and section. Restore both original header rows and column order.']);
                }
            }
            foreach ([$top, $header] as $line) if (array_filter(array_slice($line, 35), fn ($v) => trim((string) $v) !== '')) throw ValidationException::withMessages(['file' => 'Unexpected named columns after Closing Stock Value.']);
            $record = 2;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $record++;
                if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
                if ($record > 50002) throw ValidationException::withMessages(['file' => 'Limit each import to 50,000 CSV records.']);
                if (count($row) < 35 || array_filter(array_slice($row, 35), fn ($v) => trim((string) $v) !== '')) {
                    throw ValidationException::withMessages(['file' => "CSV record {$record} has shifted, missing or extra populated columns."]);
                }
                $row = array_map(fn ($v) => mb_check_encoding((string) $v, 'UTF-8') ? (string) $v : mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252'), array_slice($row, 0, 35));
                $data = ['source_values' => $row, 'source_row' => $record, 'source_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'import_warnings' => []];
                foreach (ConstructionFields::FIELDS as $i => $field) {
                    $value = trim($row[$i]);
                    $key = $field['key'];
                    $data[$key] = $value === '' ? null : $value;
                    if ($value === '') continue;
                    if ($field['type'] === 'number') {
                        $scale = in_array($key, ['unit_price', 'closing_value']) ? 2 : 3;
                        $valid = preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,'.$scale.'})?$/D', $value);
                        $number = str_replace(',', '', $value);
                        if (! $valid || (float) $number > 999999999.999) {
                            $data[$key] = null;
                            $data['import_warnings'][$key] = $field['label'].': invalid quantity/value retained in original CSV.';
                        } else $data[$key] = $number;
                    } elseif ($field['type'] === 'date') {
                        $data[$key] = $this->date($value);
                        if (! $data[$key]) $data['import_warnings'][$key] = 'Certificate due date could not be parsed; original value retained.';
                    } elseif (mb_strlen($value) > ($field['type'] === 'text' ? 255 : 10000)) {
                        $data[$key] = null;
                        $data['import_warnings'][$key] = $field['label'].': value too long; original retained.';
                    }
                }
                if (blank($data['category'])) throw ValidationException::withMessages(['file' => "CSV record {$record} requires a valid category."]);
                yield $data;
            }
        } finally { fclose($handle); }
    }

    private function date(string $value): ?string
    {
        foreach (['d-M-y', 'd-M-Y', 'd/m/Y', 'd/m/y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
                $errors = CarbonImmutable::getLastErrors();
                if ($date && (! $errors || ! ($errors['warning_count'] + $errors['error_count'])) && strcasecmp($date->format($format), $value) === 0) return $date->format('Y-m-d');
            } catch (\Throwable) {}
        }
        return null;
    }

    private function groupKey(array $row): string
    {
        return mb_strtolower(implode('|', [$row['category'], $row['section_1'], $row['section_2'], $row['description']]));
    }

    public function groupingCounts(UploadedFile $file): array
    {
        $groups = [];
        foreach ($this->rows($file) as $row) if ($row['category'] === 'BLAST GRIT' && filled($row['description'])) {
            $key = $this->groupKey($row);
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }
        return $groups;
    }

    public function prepare(array $row, array $groups, int $branchId): MiriConstructionItem
    {
        $row['branch_id'] = $branchId;
        $row['grouping_review_required'] = ($groups[$this->groupKey($row)] ?? 0) > 1 ||
            ($row['stock_balance'] === null && collect(['stock_in_qty', 'issue_location_qty', 'issue_personnel_qty', 'backload_qty'])->contains(fn ($key) => $row[$key] !== null));
        $item = new MiriConstructionItem($row);
        $item->needs_review = count($item->reviewFlags()) > 0;
        return $item;
    }

    public function preview(UploadedFile $file, int $branchId): array
    {
        $groups = $this->groupingCounts($file);
        $existing = MiriConstructionItem::withoutGlobalScopes()->where('branch_id', $branchId)->whereNotNull('normalized_tag')
            ->select('normalized_tag')->selectRaw('COUNT(*) as total')->groupBy('normalized_tag')->pluck('total', 'normalized_tag')->all();
        $report = ['records' => 0, 'needs_review' => 0, 'grouping_review' => 0, 'duplicate_records' => 0, 'categories' => [], 'samples' => [],
            'file_hash' => hash_file('sha256', $file->getRealPath())];
        $incoming = [];
        foreach ($this->rows($file) as $row) {
            $item = $this->prepare($row, $groups, $branchId);
            $report['records']++;
            $report['needs_review'] += (int) $item->needs_review;
            $report['grouping_review'] += (int) $item->grouping_review_required;
            $report['categories'][$item->category] = ($report['categories'][$item->category] ?? 0) + 1;
            $key = mb_strtolower(trim((string) $item->tag_no));
            if ($key !== '') $incoming[$key] = ($incoming[$key] ?? 0) + 1;
            if (count($report['samples']) < 5) $report['samples'][] = ['source_row' => $item->source_row, 'category' => $item->category, 'description' => $item->description, 'tag_no' => $item->tag_no, 'stock_balance' => $item->stock_balance, 'unit' => $item->unit, 'flags' => $item->reviewFlags()];
        }
        foreach ($incoming as $key => $count) if ($count + ($existing[$key] ?? 0) > 1) $report['duplicate_records'] += $count;
        if (! $report['records']) throw ValidationException::withMessages(['file' => 'No data records found.']);
        $report['already_imported'] = \Illuminate\Support\Facades\DB::table('miri_construction_imports')->where('branch_id', $branchId)->where('active_hash', $report['file_hash'])->exists();
        return $report;
    }

    // Called inside the job's transaction; no stock movements are replayed.
    public function import(UploadedFile $file, int $branchId): array
    {
        $groups = $this->groupingCounts($file);
        $result = ['created' => 0, 'needs_review' => 0];
        foreach ($this->rows($file) as $row) {
            $item = $this->prepare($row, $groups, $branchId);
            $item->save();
            $result['created']++;
            $result['needs_review'] += (int) $item->needs_review;
        }
        if (! $result['created']) throw ValidationException::withMessages(['file' => 'No data records found.']);
        return $result;
    }
}
