<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class KasperskyImportService
{
    public function parse(string $path): array
    {
        try {
            $source = app(KasperskySpreadsheetReader::class)->rows($path);
            if (! $source) {
                throw new RuntimeException('The spreadsheet has no licence rows.');
            }
            $rows = [];
            $numbers = [];
            $devices = [];
            foreach ($source as $index => $row) {
                $line = $index + 2;
                $no = trim($row['no']);
                $licence = trim($row['licence']);
                if (! ctype_digit($no) || (int) $no < 1 || isset($numbers[(int) $no])) {
                    throw new RuntimeException("Row {$line}: No must be a unique positive number.");
                }
                if (! str_contains(strtolower($licence), 'kaspersky') || strlen($licence) > 255) {
                    throw new RuntimeException("Row {$line}: a Kaspersky licence name is required.");
                }
                $numbers[(int) $no] = true;
                $parts = array_map('trim', explode("\t", trim($row['device'])));
                $device = $parts[0];
                $key = strtolower($device);
                if ($device !== '' && isset($devices[$key])) {
                    throw new RuntimeException("Row {$line}: device '{$device}' appears more than once.");
                }
                if ($device !== '') {
                    $devices[$key] = true;
                }
                $rows[] = [
                    'no' => (int) $no, 'licence' => $licence, 'device' => $device,
                    'group' => $parts[1] ?? '',
                ];
            }

            return $rows;
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }
}
