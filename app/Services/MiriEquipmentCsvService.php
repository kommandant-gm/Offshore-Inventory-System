<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\MiriInventoryCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiriEquipmentCsvService
{
    public const CARGO_CERTIFICATES = ['PADEYE MPI', 'VISUAL INSPECTION', 'WIRE SLING VALIDITY', 'CERTIFICATE OF CONFORMITY'];
    public const MACHINERY_CERTIFICATES = ['SERVICE RELIEF VALVE', 'PRESSURE GAUGE', 'SKID MPI', 'WATER MANIFOLD H.T.', 'RELAY', 'UT', 'HT', 'WINCH LOAD TEST', 'WIRE ROPE INSPECTION', 'HOOK', 'CIDB', 'LIFTING', 'LIFTED EQUIPMENT'];

    public function preview(UploadedFile $file, string $type): array
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $report = ['records' => 0, 'certificates' => 0, 'warning_records' => 0, 'missing_details' => 0, 'duplicate_records' => 0, 'samples' => [], 'file_hash' => hash_file('sha256', $file->getRealPath())];
        $tags = MajorEquipment::withoutGlobalScopes()->where('branch_id', $branch->id)->whereNotNull('tag_no')->pluck('tag_no')->map(fn ($tag) => mb_strtolower(trim($tag)))->countBy()->all();
        $incoming = [];
        foreach ($this->rows($file, $type) as $record) {
            $report['records']++;
            $report['certificates'] += count($record['certificates']);
            $report['warning_records'] += empty($record['import_warnings']) ? 0 : 1;
            $report['missing_details'] += blank($record['tag_no']) || blank($record['description']) || blank($record['current_location']) ? 1 : 0;
            $key = mb_strtolower(trim((string) $record['tag_no']));
            if ($key !== '') $incoming[$key] = ($incoming[$key] ?? 0) + 1;
            if (count($report['samples']) < 5) $report['samples'][] = $record;
        }
        foreach ($incoming as $key => $count) if ($count + ($tags[$key] ?? 0) > 1) $report['duplicate_records'] += $count;
        if ($report['records'] === 0) throw ValidationException::withMessages(['file' => 'No equipment records found in this CSV.']);
        $report['already_imported'] = DB::table('miri_inventory_imports')->where('branch_id', $branch->id)->where('inventory_type', $type)->where('file_hash', $report['file_hash'])->exists();
        return $report;
    }

    public function import(UploadedFile $file, int $userId, string $type): array
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $report = $this->preview($file, $type);
        return DB::transaction(function () use ($file, $userId, $type, $branch, $report) {
            DB::table('branches')->where('id', $branch->id)->lockForUpdate()->first();
            if (DB::table('miri_inventory_imports')->where('branch_id', $branch->id)->where('inventory_type', $type)->where('file_hash', $report['file_hash'])->exists()) {
                throw ValidationException::withMessages(['file' => 'This exact file has already been imported. Review its existing records in the register.']);
            }
            $created = 0;
            foreach ($this->rows($file, $type) as $record) {
                $certificates = $record['certificates'];
                unset($record['certificates']);
                MiriInventoryCategory::withoutGlobalScopes()->firstOrCreate(['branch_id' => $branch->id, 'name' => $record['category']], ['code' => 'MIRI-'.substr(hash('sha256', $record['category']), 0, 16), 'active' => true]);
                $item = MajorEquipment::create(['branch_id' => $branch->id, ...$record]);
                foreach ($certificates as $certificate) $item->certificates()->create(['branch_id' => $branch->id, ...$certificate]);
                $created++;
            }
            DB::table('miri_inventory_imports')->insert(['branch_id' => $branch->id, 'inventory_type' => $type, 'file_hash' => $report['file_hash'], 'filename' => mb_substr($file->getClientOriginalName(), 0, 255), 'records_count' => $created, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            return ['created' => $created, 'certificates_created' => $report['certificates'], 'duplicate_records' => $report['duplicate_records'], 'warning_records' => $report['warning_records']];
        });
    }

    private function rows(UploadedFile $file, string $type): \Generator
    {
        if (! in_array($type, ['cargo', 'machinery'], true)) throw ValidationException::withMessages(['inventory_type' => 'Choose Machinery or Cargo.']);
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) throw ValidationException::withMessages(['file' => 'Unable to read CSV.']);
        $header = false;
        $rowNumber = 0;
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                $row = array_map(fn ($value) => trim(str_replace("\xEF\xBB\xBF", '', (string) $value)), $row);
                if (! $header) {
                    if (strtoupper($row[0] ?? '') !== 'CATEGORY' || strtoupper($row[3] ?? '') !== 'DESCRIPTION') continue;
                    $cargo = str_contains(strtoupper($row[9] ?? ''), 'TAG');
                    if (count($row) !== ($type === 'cargo' ? 29 : 32) || $cargo !== ($type === 'cargo') || ! str_contains(strtoupper($row[$cargo ? 9 : 7]), 'TAG')) {
                        throw ValidationException::withMessages(['file' => 'CSV columns do not match the selected format. Select the correct Machinery or Cargo format.']);
                    }
                    if ($cargo) {
                        foreach ([1 => 'SECTION 1', 2 => 'SECTION 2', 4 => 'UNIT', 5 => 'SIZE (MODEL)', 6 => 'SIZE (TON)', 7 => 'SIZE (LENGTH)', 8 => 'QTY', 10 => 'CURRENT LOCATION', 11 => 'STATUS', 12 => 'LOCATION', 15 => 'PADEYE MPI', 17 => 'VISUAL INSPECTION', 19 => 'WIRE SLING VALIDITY'] as $column => $label) {
                            if (strtoupper(preg_replace('/\s+/', ' ', $row[$column])) !== $label) {
                                throw ValidationException::withMessages(['file' => 'Cargo column '.($column + 1)." must be {$label}. Restore the original column order before importing."]);
                            }
                        }
                    }
                    $header = true;
                    continue;
                }
                if (! array_filter($row, fn ($value) => $value !== '')) continue;
                if (blank($row[0] ?? null) && strtoupper($row[15] ?? '') === 'CERTIFICATE') continue;
                if (count($row) !== ($type === 'cargo' ? 29 : 32)) throw ValidationException::withMessages(['file' => "CSV record {$rowNumber} has an unexpected column count."]);
                yield $this->map($row, $type, $rowNumber);
            }
            if (! $header) throw ValidationException::withMessages(['file' => 'The required CATEGORY / SECTION / DESCRIPTION header was not found.']);
        } finally {
            fclose($handle);
        }
    }

    private function map(array $row, string $type, int $rowNumber): array
    {
        $v = fn ($i) => ($row[$i] ?? '') === '' ? null : $row[$i];
        $cargo = $type === 'cargo';
        $warnings = [];
        foreach ($row as $i => $value) {
            if (! mb_check_encoding($value, 'UTF-8') || (mb_strlen($value) > 255 && ! in_array($i, $cargo ? [22, 27, 28] : [30, 31], true))) {
                throw ValidationException::withMessages(['file' => "Record {$rowNumber}, column ".($i + 1).' contains invalid encoding or too much text. Save as UTF-8 CSV and review this cell.']);
            }
        }
        $section = $v(1);
        if (in_array(strtoupper((string) $section), ['MACHINARY', 'MACHINERY'], true)) $section = 'Machinery';
        if ($cargo && strtoupper(trim((string) $section)) !== 'CARGO SET') $warnings[] = 'Unexpected Cargo section; original section retained.';
        $record = ['inventory_type' => $type, 'category' => $v(0) ?: 'MAJOR EQUIPMENT', 'section_1' => $section, 'section_2' => $v(2), 'description' => $v(3), 'unit' => $v(4),
            'tag_no' => $v($cargo ? 9 : 7), 'current_location' => $v($cargo ? 10 : 8), 'status' => $v($cargo ? 11 : 9), 'issue_out_location' => $v($cargo ? 12 : 10), 'active' => true];
        if ($cargo) {
            $qty = $v(8);
            if ($qty !== null && (! is_numeric($qty) || (float) $qty < 0 || (float) $qty > 9999999999.99 || ! preg_match('/^\d+(\.\d{1,2})?$/', $qty))) {
                $warnings[] = 'Quantity could not be interpreted: '.$qty;
                $qty = null;
            }
            $record += ['size_model' => $v(5), 'size_ton' => $v(6), 'size_length' => $v(7), 'quantity' => $qty, 'remarks' => $v(22)];
        } else {
            $record += ['model_brand' => $v(5), 'serial_no' => $v(6)];
            $record['status'] = match (strtoupper((string) $record['status'])) { 'IN-USE' => 'In Use', 'STAND BY' => 'Standby', 'CHECK & REPAIR' => 'Under Repair', 'DAMAGED' => 'Damaged', default => $record['status'] };
        }
        foreach (['issue_out_cog' => $cargo ? 13 : 11, 'received_backload_cog' => $cargo ? 14 : 12] as $prefix => $column) {
            $raw = $v($column);
            $date = null;
            $number = $raw;
            if ($raw && preg_match('/\b\d{1,4}[\/-](?:\d{1,2}|[A-Za-z]{3})[\/-]\d{2,4}\b/', $raw, $match)) {
                $date = $this->date($match[0]);
                if ($date) $number = trim(str_replace($match[0], '', $raw), " \t\r\n,;-") ?: null;
                else $warnings[] = "Invalid {$prefix} date: ".$match[0];
            }
            $record[$prefix.'_no'] = $number;
            $record[$prefix.'_date'] = $date;
        }
        foreach (['mr_request', 'purchase_order', 'delivery_order', 'supplier', 'unfit_report', 'write_off_reference'] as $offset => $field) $record[$field] = $v(($cargo ? 23 : 26) + $offset);
        $certificates = [];
        foreach ($cargo ? self::CARGO_CERTIFICATES : self::MACHINERY_CERTIFICATES as $offset => $certificateType) {
            $column = $cargo ? 15 + $offset * 2 : 13 + $offset;
            $raw = $v($column);
            $expiry = $cargo && $offset < 3 ? $v($column + 1) : null;
            if ($raw === null && $expiry === null) continue;
            $date = null;
            $number = $raw;
            if ($cargo && $expiry !== null) {
                $date = $this->date($expiry);
                if (! $date) $warnings[] = "{$certificateType}: invalid expiry date {$expiry}";
            } elseif (! $cargo && $raw && preg_match('/\b\d{1,4}[\/-](?:\d{1,2}|[A-Za-z]{3})[\/-]\d{2,4}\b/', $raw, $match)) {
                $date = $this->date($match[0]);
                if ($date) $number = trim(str_replace($match[0], '', $raw), " \t\r\n,;-") ?: null;
                else $warnings[] = "{$certificateType}: invalid date ".$match[0];
            }
            $certificates[] = ['certificate_type' => $certificateType, 'certificate_no' => $number, 'expiry_date' => $date, 'raw_value' => $cargo ? trim(($raw ?? '').($expiry ? ' | Expiry: '.$expiry : '')) : $raw];
        }
        return [...$record, 'certificates' => $certificates, 'import_warnings' => $warnings ?: null, 'source_values' => ['csv_record' => $rowNumber, 'columns' => $row]];
    }

    private function date(string $raw): ?string
    {
        foreach (['!d-M-y', '!d-M-Y', '!Y-m-d', '!d/m/Y', '!d/m/y', '!d-m-Y', '!d-m-y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($raw));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d');
        }
        return null;
    }
}
