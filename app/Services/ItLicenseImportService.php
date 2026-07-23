<?php

namespace App\Services;

use App\Models\ItLicense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ItLicenseImportService
{
    private const TYPES = ['subscription', 'perpetual', 'volume', 'oem', 'trial'];

    public function analyse(string $path): array
    {
        $rows = $this->rows($path);
        $valid = [];
        $warnings = [];
        $rejected = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $mapped = $this->map($row);
            } catch (RuntimeException $exception) {
                $rejected[] = "Row {$line}: {$exception->getMessage()}";

                continue;
            }

            if ($mapped['software_name'] === '') {
                $rejected[] = "Row {$line}: licence/software name is missing.";

                continue;
            }

            if ($mapped['seats_assigned'] === 1 && blank($mapped['assigned_to'])) {
                $warnings[] = "Row {$line}: marked as assigned but the assignee name is blank.";
            }

            $valid[] = ['line' => $line, 'data' => $mapped];
        }

        return [
            'total' => count($rows),
            'ready' => count($valid),
            'assigned' => collect($valid)->where('data.seats_assigned', 1)->count(),
            'available' => collect($valid)->where('data.seats_assigned', 0)->count(),
            'warning_count' => count($warnings),
            'rejected_count' => count($rejected),
            'warnings' => array_slice($warnings, 0, 100),
            'rejected' => array_slice($rejected, 0, 100),
            'valid' => $valid,
        ];
    }

    public function import(string $path): array
    {
        $report = $this->analyse($path);

        DB::transaction(function () use ($report) {
            $nextNumber = $this->nextLicenseNumber();

            foreach ($report['valid'] as $entry) {
                ItLicense::create([
                    'license_code' => $this->licenseCode($nextNumber++),
                    ...$entry['data'],
                ]);
            }
        });

        return collect($report)->except('valid')->all();
    }

    private function map(array $row): array
    {
        $get = fn (string $key): string => trim((string) ($row[$key] ?? ''));
        $type = Str::lower($get('license type'));

        if (! in_array($type, self::TYPES, true)) {
            throw new RuntimeException(
                $type === ''
                    ? 'licence type is missing.'
                    : "licence type \"{$get('license type')}\" is not supported."
            );
        }

        $hasLicense = match (Str::lower($get('has license'))) {
            'yes', 'y', '1', 'true' => true,
            'no', 'n', '0', 'false' => false,
            default => throw new RuntimeException('Has License must be Yes or No.'),
        };

        $legacyAssignee = $get('checked out to');
        $userId = $get('user id');
        $category = $get('category');
        $remarks = collect([
            $userId !== '' ? "User ID: {$userId}" : null,
            $category !== '' ? "Category: {$category}" : null,
            ! $hasLicense && $legacyAssignee !== '' ? "Legacy checked out to: {$legacyAssignee}" : null,
            'Imported from the software licence spreadsheet.',
        ])->filter()->implode(' | ');

        return [
            'software_name' => $get('license'),
            'vendor' => $this->vendor($get('license')),
            'license_type' => $type,
            'license_key' => $get('license key (if available)') ?: null,
            'seats_total' => 1,
            'seats_assigned' => $hasLicense ? 1 : 0,
            'assigned_to' => $hasLicense && $legacyAssignee !== '' ? $legacyAssignee : null,
            'department' => null,
            'purchase_date' => null,
            'expiry_date' => $this->date($get('expiry date')),
            'auto_renew' => false,
            'renewal_cost' => null,
            'supplier' => null,
            'purchase_reference' => null,
            'active' => true,
            'remarks' => $remarks,
        ];
    }

    private function nextLicenseNumber(): int
    {
        return ItLicense::query()
            ->where('license_code', 'like', 'LIC-%')
            ->pluck('license_code')
            ->map(fn (string $code): int => preg_match('/^LIC-(\d+)$/i', $code, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;
    }

    private function licenseCode(int $number): string
    {
        return 'LIC-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function vendor(string $software): ?string
    {
        $software = Str::upper($software);

        return match (true) {
            str_contains($software, 'MICROSOFT'), str_contains($software, 'POWER BI') => 'Microsoft',
            str_contains($software, 'ADOBE') => 'Adobe',
            str_contains($software, 'FOXIT') => 'Foxit',
            str_contains($software, 'AUTOCAD') => 'Autodesk',
            str_contains($software, 'PRIMAVERA') => 'Oracle',
            default => null,
        };
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial <= 0) {
                throw new RuntimeException("expiry date \"{$value}\" is invalid.");
            }

            return CarbonImmutable::create(1899, 12, 30)
                ->addDays((int) floor($serial))
                ->format('Y-m-d');
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            throw new RuntimeException("expiry date \"{$value}\" is invalid.");
        }
    }

    private function rows(string $path): array
    {
        return Str::lower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx'
            ? $this->xlsxRows($path)
            : $this->csvRows($path);
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('Unable to read import file.');
        }

        $firstLine = (string) fgets($handle);
        $delimiters = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);
        rewind($handle);

        $headers = array_map(fn ($value) => $this->header($value), fgetcsv($handle, 0, $delimiter) ?: []);
        $this->validateHeaders($headers);
        $rows = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $values = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
        }

        fclose($handle);

        return $rows;
    }

    private function xlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP Zip extension is required for XLSX imports.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $xml = simplexml_load_string($sharedXml);
            foreach ($xml?->xpath('//*[local-name()="si"]') ?: [] as $item) {
                $shared[] = implode('', array_map(
                    fn ($node) => (string) $node,
                    $item->xpath('.//*[local-name()="t"]') ?: []
                ));
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheet) {
            throw new RuntimeException('The first worksheet could not be read.');
        }

        $xml = simplexml_load_string($sheet);
        $matrix = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $matches);
                $column = $this->columnIndex($matches[0] ?? 'A');
                $value = (string) $cell->v;

                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = implode('', array_map(
                        fn ($node) => (string) $node,
                        $cell->xpath('.//*[local-name()="t"]') ?: []
                    ));
                }

                $values[$column] = trim($value);
            }

            if ($values) {
                $maximum = max(array_keys($values));
                $matrix[] = array_map(fn ($index) => $values[$index] ?? '', range(0, $maximum));
            }
        }

        $headerIndex = collect($matrix)->search(
            fn ($row) => collect($row)->contains(fn ($value) => $this->header($value) === 'license')
                && collect($row)->contains(fn ($value) => $this->header($value) === 'license type')
        );

        if ($headerIndex === false) {
            throw new RuntimeException('Could not find the licence headers in the first worksheet.');
        }

        $matrix = array_slice($matrix, (int) $headerIndex);
        $headers = array_map(fn ($value) => $this->header($value), array_shift($matrix) ?? []);
        $this->validateHeaders($headers);

        return collect($matrix)
            ->reject(fn ($values) => collect($values)->every(fn ($value) => trim((string) $value) === ''))
            ->map(function ($values) use ($headers) {
                $values = array_pad($values, count($headers), '');

                return array_combine($headers, array_slice($values, 0, count($headers)));
            })
            ->values()
            ->all();
    }

    private function validateHeaders(array $headers): void
    {
        $missing = collect(['license', 'license type', 'has license'])
            ->reject(fn (string $header) => in_array($header, $headers, true));

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing required column(s): '.$missing->implode(', ').'.');
        }
    }

    private function columnIndex(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $character) {
            $number = $number * 26 + (ord($character) - 64);
        }

        return $number - 1;
    }

    private function header(mixed $value): string
    {
        return Str::lower(trim((string) preg_replace(
            '/\s+/u',
            ' ',
            str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ' ', (string) $value)
        )));
    }
}
