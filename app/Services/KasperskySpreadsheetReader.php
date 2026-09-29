<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class KasperskySpreadsheetReader
{
    public function rows(string $path): array
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
        fclose($handle);
        $this->validateHeaders($headers);
        $handle = fopen($path, 'rb');
        fgetcsv($handle, 0, $delimiter);
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

        foreach (['xl/sharedStrings.xml', 'xl/worksheets/sheet1.xml'] as $entry) {
            $stat = $zip->statName($entry);
            if ($stat && $stat['size'] > 20 * 1024 * 1024) {
                $zip->close();
                throw new RuntimeException('The Excel worksheet is too large. Please use CSV.');
            }
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $xml = $this->xml($sharedXml);
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

        $xml = $this->xml($sheet);
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
            fn ($row) => collect($row)->contains(fn ($value) => $this->header($value) === 'licence')
                && collect($row)->contains(fn ($value) => $this->header($value) === 'device')
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
        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('The spreadsheet contains duplicate column headers.');
        }
        $missing = collect(['no', 'licence', 'device'])
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

    private function xml(string $content): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException('The Excel file contains an invalid worksheet.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function header(mixed $value): string
    {
        return str_replace('license', 'licence', Str::lower(trim((string) preg_replace(
            '/\s+/u',
            ' ',
            str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ' ', (string) $value)
        ))));
    }
}
