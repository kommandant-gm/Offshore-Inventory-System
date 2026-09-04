<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MiriRentalItem;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MiriRentalImportService
{
    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) throw ValidationException::withMessages(['file' => 'Unable to read the uploaded Rental CSV file.']);
        fgetcsv($handle); fgetcsv($handle);
        $branch = Branch::query()->where('code', 'MIRI')->first();
        if (! $branch) { fclose($handle); throw ValidationException::withMessages(['file' => 'MIRI branch is not configured.']); }

        $summary = ['rows_seen' => 0, 'created' => 0, 'skipped_blank' => 0, 'skipped_duplicate' => 0];
        DB::transaction(function () use ($handle, $branch, &$summary): void {
            while (($row = fgetcsv($handle)) !== false) {
                $summary['rows_seen']++;
                if ($this->blank($row)) { $summary['skipped_blank']++; continue; }
                $serial = $this->value($row, 4);
                if ($serial && MiriRentalItem::query()->where('branch_id', $branch->id)->where('serial_tag_equipment_no', $serial)->exists()) { $summary['skipped_duplicate']++; continue; }
                [$issueNo, $issueDate] = $this->reference($this->value($row, 10), $this->value($row, 11));
                [$receivedNo, $receivedDate] = $this->reference($this->value($row, 13));
                [$offhireNo, $offhireDate] = $this->reference($this->value($row, 14));
                [$returnNo, $returnDate] = $this->reference($this->value($row, 15));
                [$mrNo, $mrDate] = $this->reference($this->value($row, 16));
                [$poNo, $poDate] = $this->reference($this->value($row, 17));
                [$doNo, $doDate] = $this->reference($this->value($row, 18));
                [$onhireNo, $onhireDate] = $this->reference($this->value($row, 19));
                MiriRentalItem::create([
                    'branch_id' => $branch->id, 'category' => $this->value($row, 0), 'section_1' => $this->value($row, 1), 'section_2' => $this->value($row, 2),
                    'description' => $this->value($row, 3), 'serial_tag_equipment_no' => $serial, 'unit' => $this->value($row, 5), 'supplier' => $this->value($row, 6),
                    'project_contract' => $this->value($row, 7), 'current_location' => $this->value($row, 8), 'rental_due_date' => $this->date($this->value($row, 9)),
                    'issue_out_cog_no' => $issueNo, 'issue_out_cog_date' => $issueDate, 'received_backload_from_location' => $this->value($row, 12),
                    'received_backload_cog_no' => $receivedNo, 'received_backload_cog_date' => $receivedDate, 'offhire_certificate_no' => $offhireNo, 'offhire_certificate_date' => $offhireDate,
                    'return_cog_no' => $returnNo, 'return_cog_date' => $returnDate, 'mr_no' => $mrNo, 'mr_date' => $mrDate, 'po_or_sr_no' => $poNo, 'po_or_sr_date' => $poDate,
                    'do_no' => $doNo, 'do_date' => $doDate, 'onhire_certificate_no' => $onhireNo, 'onhire_certificate_date' => $onhireDate,
                    'status' => $this->status($offhireNo, $returnNo, $receivedNo, $issueNo, $this->date($this->value($row, 9))), 'remarks' => $this->value($row, 20), 'active' => true,
                ]);
                $summary['created']++;
            }
        });
        fclose($handle);
        return $summary;
    }

    private function value(array $row, int $index): ?string
    {
        $value = trim((string) ($row[$index] ?? ''));
        return $value === '' || $value === '-' ? null : preg_replace('/\s+/', ' ', preg_replace('/[\r\n]+/', ' ', $value));
    }

    private function reference(?string $raw, ?string $separateDate = null): array
    {
        if (! $raw) return [null, $this->date($separateDate)];
        $date = $this->date($separateDate ?: $raw);
        $number = trim((string) (preg_replace('/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/', '', $raw) ?? $raw), " \t\r\n,;-");
        return [$number === '' ? null : $number, $date];
    }

    private function date(?string $raw): ?string
    {
        if (! $raw || ! preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})\b/', $raw, $match)) return null;
        $year = (int) $match[3]; if ($year < 100) $year += 2000;
        try { return Carbon::createFromDate($year, (int) $match[2], (int) $match[1])->toDateString(); } catch (\Throwable) { return null; }
    }

    private function status(?string $offhire, ?string $return, ?string $received, ?string $issued, ?string $due): string
    {
        if ($return) return 'Returned to Supplier'; if ($offhire) return 'Off Hire'; if ($received) return 'Received Backload';
        if ($issued) return $due && $due < now()->toDateString() ? 'Overdue' : 'Issued'; return 'On Hire';
    }

    private function blank(array $row): bool
    {
        foreach ($row as $value) if (trim((string) $value) !== '') return false;
        return true;
    }
}
