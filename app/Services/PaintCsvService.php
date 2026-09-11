<?php
namespace App\Services;

use App\Models\MiriPaintItem;
use App\Support\PaintFields;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaintCsvService
{
    private function header(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim(str_replace("\xEF\xBB\xBF", '', (string) $value))));
    }
    public function parseDate(string $value): ?string
    {
        foreach (['d/m/Y', 'd.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, trim($value));
                $errors = CarbonImmutable::getLastErrors();
                if ($date && (! $errors || ! ($errors['warning_count'] + $errors['error_count'])) && $date->format($format) === trim($value)) return $date->format('Y-m-d');
            } catch (\Throwable) {}
        }
        return null;
    }
    public function dates(?string $value): array
    {
        $data = ['manufacture_date' => null, 'best_before_date' => null, 'unconfirmed_date' => null, 'date_status' => 'not_recorded'];
        if (blank($value)) return $data;
        $data['date_status'] = 'unconfirmed';
        if (preg_match('/^\s*(\d{2}[\/.]\d{2}[\/.]\d{4})\s*[-–—]\s*(\d{2}[\/.]\d{2}[\/.]\d{4})\s*$/u', $value, $parts)) {
            $start = $this->parseDate($parts[1]); $end = $this->parseDate($parts[2]);
            if ($start && $end && $start <= $end) {
                $data['manufacture_date'] = $start; $data['best_before_date'] = $end; $data['date_status'] = 'confirmed';
            }
        } else $data['unconfirmed_date'] = $this->parseDate($value);
        return $data;
    }
    public function rows(UploadedFile $file): \Generator
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) throw ValidationException::withMessages(['file' => 'Unable to read CSV.']);
        try {
            $top = fgetcsv($handle, 0, ',', '"', '');
            $middle = fgetcsv($handle, 0, ',', '"', '');
            $header = fgetcsv($handle, 0, ',', '"', '');
            $expected = [0 => 'CATEGORY & SECTION', 13 => 'PAINT DATA', 15 => 'ISSUE OUT TO LOCATION', 20 => 'MR REQUEST',
                21 => 'PURCHASE ORDER', 23 => 'RECEIVE (STOCK-IN)', 26 => 'RECEIVED BACKLOAD', 30 => 'UNFIT & WRITE-OFF'];
            $sub = [4 => 'OPENING STOCK', 8 => 'CLOSING STOCK'];
            if (! is_array($top) || ! is_array($middle) || ! is_array($header)) throw ValidationException::withMessages(['file' => 'All three original Paint header rows are required.']);
            for ($i = 0; $i < 32; $i++) {
                if ($this->header($top[$i] ?? null) !== ($expected[$i] ?? '') ||
                    $this->header($middle[$i] ?? null) !== ($sub[$i] ?? '') ||
                    $this->header($header[$i] ?? null) !== $this->header(PaintFields::FIELDS[$i]['label'])) {
                    throw ValidationException::withMessages(['file' => 'Column '.($i + 1).' does not match the Paint template. Keep all three header levels and original column order.']);
                }
            }
            foreach ([$top, $middle, $header] as $line) if (array_filter(array_slice($line, 32), fn ($v) => trim((string) $v) !== '')) throw ValidationException::withMessages(['file' => 'Unexpected columns after Write-Off Reference.']);
            $record = 3; $count = 0;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $record++;
                if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
                if (++$count > 50000 || count($row) < 32 || array_filter(array_slice($row, 32), fn ($v) => trim((string) $v) !== '')) {
                    throw ValidationException::withMessages(['file' => "CSV record {$record}: shifted/extra columns or 50,000-row limit exceeded."]);
                }
                $row = array_map(fn ($v) => mb_check_encoding((string) $v, 'UTF-8') ? (string) $v : mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252'), array_slice($row, 0, 32));
                $data = ['source_values' => $row, 'source_row' => $record, 'source_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'import_warnings' => []];
                foreach (PaintFields::FIELDS as $i => $field) {
                    $value = trim($row[$i]); $key = $field['key'];
                    $data[$key] = $value === '' ? null : $value;
                    if ($value === '') continue;
                    if (in_array($field['type'], ['number', 'money'])) {
                        $scale = $field['type'] === 'money' ? 2 : 3;
                        // A comma followed by one/two digits is a decimal, not a thousands separator.
                        if (preg_match('/^\d+,\d{1,2}$/D', $value)) {
                            $value = str_replace(',', '.', $value);
                            $data['import_warnings'][$key] = $field['label'].': decimal comma converted to '.$value.'; verify against original.';
                        }
                        $valid = preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,'.$scale.'})?$/D', $value);
                        $number = str_replace(',', '', $value);
                        if (! $valid || (float) $number > 999999999.99) {
                            $data[$key] = null;
                            $data['import_warnings'][$key] = $field['label'].': invalid quantity/value; original retained.';
                        } else $data[$key] = $number;
                    } elseif (mb_strlen($value) > ($field['type'] === 'text' ? 255 : 10000)) {
                        throw ValidationException::withMessages(['file' => "CSV record {$record}: ".$field['label'].' exceeds field length.']);
                    }
                }
                if (blank($data['category'])) throw ValidationException::withMessages(['file' => "CSV record {$record} requires a category."]);
                yield [...$data, ...$this->dates($data['original_date'])];
            }
        } finally { fclose($handle); }
    }
    public function preview(UploadedFile $file, int $branchId): array
    {
        $report = ['records' => 0, 'needs_review' => 0, 'unconfirmed_dates' => 0, 'paired_dates' => 0, 'missing_dates' => 0,
            'duplicate_records' => 0, 'categories' => [], 'samples' => [], 'date_reviews' => [], 'conversions' => [],
            'file_hash' => hash_file('sha256', $file->getRealPath())];
        $existing = MiriPaintItem::withoutGlobalScopes()->where('branch_id', $branchId)->whereNotNull('match_key')
            ->select('match_key')->selectRaw('COUNT(*) as total')->groupBy('match_key')->pluck('total', 'match_key')->all();
        $incoming = [];
        foreach ($this->rows($file) as $row) {
            $item = new MiriPaintItem($row); $flags = $item->reviewFlags();
            $report['records']++; $report['needs_review'] += (int) (count($flags) > 0);
            $report['unconfirmed_dates'] += (int) ($item->date_status === 'unconfirmed');
            $report['paired_dates'] += (int) ($item->date_status === 'confirmed');
            $report['missing_dates'] += (int) ($item->date_status === 'not_recorded');
            $report['categories'][$item->section_2 ?? 'Not recorded'] = ($report['categories'][$item->section_2 ?? 'Not recorded'] ?? 0) + 1;
            $key = MiriPaintItem::matchingKey($item->description, $item->batch_no, $item->current_location);
            if ($key) $incoming[$key] = ($incoming[$key] ?? 0) + 1;
            if (count($report['samples']) < 5) $report['samples'][] = ['source_row' => $item->source_row, 'description' => $item->description, 'batch_no' => $item->batch_no, 'original_date' => $item->original_date, 'date_status' => $item->date_status, 'flags' => $flags];
            if ($item->date_status === 'unconfirmed' && count($report['date_reviews']) < 10) $report['date_reviews'][] = ['row' => $item->source_row, 'value' => $item->original_date];
            foreach ($item->import_warnings ?? [] as $field => $warning) if (count($report['conversions']) < 10) $report['conversions'][] = ['row' => $item->source_row, 'message' => $warning];
        }
        foreach ($incoming as $key => $count) if ($count + ($existing[$key] ?? 0) > 1) $report['duplicate_records'] += $count;
        if (! $report['records']) throw ValidationException::withMessages(['file' => 'No populated data rows found.']);
        $report['already_imported'] = DB::table('miri_paint_imports')->where('branch_id', $branchId)->where('active_hash', $report['file_hash'])->exists();
        return $report;
    }
    // The caller wraps the whole import in one transaction.
    public function import(UploadedFile $file, int $branchId): array
    {
        $result = ['created' => 0, 'needs_review' => 0, 'unconfirmed_dates' => 0];
        foreach ($this->rows($file) as $row) {
            $item = MiriPaintItem::create([...$row, 'branch_id' => $branchId]);
            $result['created']++; $result['needs_review'] += (int) $item->needs_review;
            $result['unconfirmed_dates'] += (int) ($item->date_status === 'unconfirmed');
        }
        if (! $result['created']) throw ValidationException::withMessages(['file' => 'No populated data rows found.']);
        return $result;
    }
}
