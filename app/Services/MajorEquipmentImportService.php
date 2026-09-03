<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\MajorEquipmentCertificate;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MajorEquipmentImportService
{
    private const CERTIFICATE_COLUMNS = [
        13 => 'SERVICE RELIEF VALVE', 14 => 'PRESSURE GAUGE', 15 => 'SKID MPI',
        16 => 'WATER MANIFOLD H.T.', 17 => 'RELAY', 18 => 'UT', 19 => 'HT',
        20 => 'WINCH LOAD TEST', 21 => 'WIRE ROPE INSPECTION', 22 => 'HOOK',
        23 => 'CIDB', 24 => 'LIFTING', 25 => 'LIFTED EQUIPMENT',
    ];

    public function import(UploadedFile $file, int $userId): array
    {
        [$rows, $branch] = $this->read($file);
        $summary = ['rows_seen' => count($rows), 'created' => 0, 'skipped_blank' => 0, 'skipped_orphan' => 0, 'certificates_created' => 0];

        DB::transaction(function () use ($rows, $branch, &$summary): void {
            foreach ($rows as $row) {
                if ($this->blank($row)) { $summary['skipped_blank']++; continue; }
                if (trim((string) ($row[0] ?? '')) === '') { $summary['skipped_orphan']++; continue; }

                $tagNo = $this->value($row, 7);
                if ($tagNo && MajorEquipment::query()->where('branch_id', $branch->id)->where('tag_no', $tagNo)->exists()) {
                    $summary['skipped_orphan']++;
                    continue;
                }

                $equipment = MajorEquipment::create([
                    'branch_id' => $branch->id,
                    'category' => $this->value($row, 0) ?: 'MAJOR EQUIPMENT',
                    'section_1' => $this->value($row, 1), 'section_2' => $this->value($row, 2),
                    'description' => $this->value($row, 3), 'unit' => $this->value($row, 4),
                    'model_brand' => $this->value($row, 5), 'serial_no' => $this->value($row, 6),
                    'tag_no' => $tagNo, 'current_location' => $this->value($row, 8),
                    'status' => $this->status($this->value($row, 9)),
                    'issue_out_location' => $this->value($row, 10),
                    ...$this->cog($row, 11, 'issue_out_cog'), ...$this->cog($row, 12, 'received_backload_cog'),
                    'mr_request' => $this->value($row, 26), 'purchase_order' => $this->value($row, 27),
                    'delivery_order' => $this->value($row, 28), 'supplier' => $this->value($row, 29),
                    'unfit_report' => $this->value($row, 30), 'write_off_reference' => $this->value($row, 31),
                    'active' => true,
                ]);

                foreach (self::CERTIFICATE_COLUMNS as $column => $type) {
                    $raw = $this->value($row, $column);
                    if ($raw === null) continue;
                    $date = $this->dateFrom($raw);
                    MajorEquipmentCertificate::create([
                        'miri_inventory_item_id' => $equipment->id, 'branch_id' => $branch->id,
                        'certificate_type' => $type, 'certificate_no' => $this->certificateNo($raw),
                        'expiry_date' => $date, 'raw_value' => $raw,
                    ]);
                    $summary['certificates_created']++;
                }
                $summary['created']++;
            }
        });

        return $summary;
    }

    private function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) throw ValidationException::withMessages(['file' => 'Unable to read the uploaded CSV file.']);
        fgetcsv($handle); // grouped header row
        fgetcsv($handle); // actual header row
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
        fclose($handle);
        $branch = Branch::query()->where('code', 'MIRI')->first();
        if (! $branch) throw ValidationException::withMessages(['file' => 'MIRI branch is not configured.']);
        return [$rows, $branch];
    }

    private function value(array $row, int $index): ?string
    {
        $value = trim((string) ($row[$index] ?? ''));
        return $value === '' ? null : $value;
    }

    private function blank(array $row): bool
    {
        foreach ($row as $value) if (trim((string) $value) !== '') return false;
        return true;
    }

    private function status(?string $value): ?string
    {
        return match (Str::upper((string) $value)) {
            'IN-USE' => 'In Use', 'STAND BY' => 'Standby', 'CHECK & REPAIR' => 'Under Repair', 'DAMAGED' => 'Damaged', default => $value,
        };
    }

    private function cog(array $row, int $index, string $prefix): array
    {
        $raw = $this->value($row, $index);
        if (! $raw) return [$prefix.'_no' => null, $prefix.'_date' => null];
        return [$prefix.'_no' => $this->certificateNo($raw), $prefix.'_date' => $this->dateFrom($raw)];
    }

    private function certificateNo(string $raw): ?string
    {
        $value = preg_replace('/[\r\n]+/', ' ', trim($raw)) ?? trim($raw);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/', '', $value) ?? $value;
        $value = trim($value, " \t\r\n,;-");
        return $value === '' ? null : $value;
    }

    private function dateFrom(string $raw): ?string
    {
        if (! preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})\b/', $raw, $match)) return null;
        $year = (int) $match[3]; if ($year < 100) $year += 2000;
        try { return Carbon::createFromDate($year, (int) $match[2], (int) $match[1])->toDateString(); } catch (\Throwable) { return null; }
    }
}
