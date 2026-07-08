<?php

namespace App\Actions\Inventory;

use App\Enums\CategoryType;
use App\Enums\InventoryTransactionType;
use App\Enums\LocationType;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryLocationBalanceService;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportInventoryCsvAction
{
    private const REQUIRED_COLUMNS = [
        'proposed_item_code',
        'category_name',
        'location_name',
        'description',
        'uom',
        'opening_stock',
        'total_received',
        'total_issued',
        'interloc_transfer',
        'material_return',
        'physical_adjustment',
        'price_adjustment',
        'other_misc',
        'closing_stock',
        'unit_price',
    ];

    public function __construct(
        private readonly RecordInventoryTransactionAction $recordInventoryTransactionAction,
        private readonly InventoryLocationBalanceService $locationBalanceService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function execute(UploadedFile $file, CarbonInterface $movementDate, int $userId): array
    {
        [$headers, $rows] = $this->parseCsv($file);
        $this->ensureRequiredColumns($headers);

        return DB::transaction(function () use ($rows, $movementDate, $userId) {
            $summary = [
                'rows_processed' => 0,
                'items_created' => 0,
                'items_skipped' => 0,
                'movements_created' => 0,
            ];

            foreach ($rows as $index => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $summary['rows_processed']++;
                $this->validateRow($row, $index + 2);

                $itemCode = trim((string) $row['proposed_item_code']);
                if (InventoryItem::query()->where('item_code', $itemCode)->exists()) {
                    $summary['items_skipped']++;
                    continue;
                }

                $category = $this->resolveCategory(trim((string) $row['category_name']));
                $location = $this->resolveLocation(trim((string) $row['location_name']));
                $itemRemarks = $this->buildItemRemarks($row);

                $item = InventoryItem::query()->create([
                    'item_code' => $itemCode,
                    'description' => trim((string) $row['description']),
                    'category_id' => $category->id,
                    'uom' => trim((string) $row['uom']),
                    'default_location_id' => $location->id,
                    'opening_stock' => $this->decimal($row['opening_stock']),
                    'standard_cost' => $this->decimal($row['unit_price']),
                    'minimum_stock' => null,
                    'rack_no' => $this->nullableString($row['rack_no'] ?? null),
                    'active' => $this->booleanOrDefault($row['active'] ?? null, true),
                    'remarks' => $itemRemarks,
                ]);

                $this->locationBalanceService->initializeItem($item->fresh(['locationBalances']));

                foreach ($this->movementPayloads($row, $location->id, $movementDate->toDateString()) as $payload) {
                    $this->recordInventoryTransactionAction->persist($item, $payload, $userId);
                    $summary['movements_created']++;
                }

                $summary['items_created']++;
            }

            $this->auditLogger->record(
                module: 'assets',
                event: 'imported',
                summary: "Imported {$summary['items_created']} stock items from CSV.",
                after: $summary + ['movement_date' => $movementDate->toDateString()],
                user: User::query()->find($userId),
            );

            return $summary;
        });
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map([$this, 'normalizeHeader'], $data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $data[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return [$headers ?? [], $rows];
    }

    private function ensureRequiredColumns(array $headers): void
    {
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => 'CSV is missing required columns: '.implode(', ', $missing),
        ]);
    }

    private function validateRow(array $row, int $lineNumber): void
    {
        $required = [
            'proposed_item_code',
            'category_name',
            'location_name',
            'description',
            'uom',
        ];

        foreach ($required as $column) {
            if (trim((string) ($row[$column] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'file' => "CSV row {$lineNumber} is missing {$column}.",
                ]);
            }
        }
    }

    private function resolveCategory(string $name): Category
    {
        $existing = Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            if ($existing->type !== CategoryType::Asset && $existing->type !== CategoryType::Both) {
                $existing->update(['type' => CategoryType::Both]);
            }

            return $existing;
        }

        return Category::query()->create([
            'code' => $this->nextUniqueCode('categories', $name, 'CAT'),
            'name' => $name,
            'type' => CategoryType::Asset,
            'active' => true,
        ]);
    }

    private function resolveLocation(string $name): Location
    {
        $existing = Location::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Location::query()->create([
            'code' => $this->nextUniqueCode('locations', $name, 'LOC'),
            'name' => $name,
            'type' => LocationType::Yard,
            'active' => true,
        ]);
    }

    private function nextUniqueCode(string $table, string $name, string $prefix): string
    {
        $slug = Str::upper(Str::substr(Str::of($name)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->toString(), 0, 16));
        $base = $prefix.'-'.($slug !== '' ? $slug : 'AUTO');
        $candidate = $base;
        $suffix = 1;

        while (DB::table($table)->where('code', $candidate)->exists()) {
            $candidate = sprintf('%s-%02d', $base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function buildItemRemarks(array $row): ?string
    {
        $notes = [];

        if ($this->nullableString($row['remarks'] ?? null)) {
            $notes[] = trim((string) $row['remarks']);
        }

        if (abs($this->decimal($row['interloc_transfer'] ?? 0)) > 0.00001) {
            $notes[] = 'Inter-location transfer total from import sheet was not posted automatically.';
        }

        if (abs($this->decimal($row['price_adjustment'] ?? 0)) > 0.00001) {
            $notes[] = 'Price adjustment total from import sheet was not posted automatically.';
        }

        return $notes === [] ? null : implode(' | ', $notes);
    }

    private function movementPayloads(array $row, int $locationId, string $movementDate): array
    {
        $common = [
            'transaction_date' => $movementDate,
            'location_id' => $locationId,
            'unit_cost' => $this->decimal($row['unit_price']),
            'po_no' => $this->nullableString($row['purchase_order_no'] ?? null),
            'do_no' => $this->nullableString($row['delivery_order_no'] ?? null),
        ];

        $movements = [];

        $add = function (InventoryTransactionType $type, float $quantity, array $extra = []) use (&$movements, $common): void {
            if (abs($quantity) < 0.00001) {
                return;
            }

            $movements[] = [
                ...$common,
                'transaction_type' => $type->value,
                'quantity' => $quantity,
                'remarks' => $extra['remarks'] ?? null,
                ...$extra,
            ];
        };

        $sheetRemarks = $this->nullableString($row['remarks'] ?? null);

        $add(
            InventoryTransactionType::Receive,
            $this->decimal($row['total_received']),
            ['remarks' => $this->movementRemark('Imported monthly received total.', $sheetRemarks)]
        );

        $issueQuantity = $this->decimal($row['total_issued']);
        if ($issueQuantity > 0) {
            $add(InventoryTransactionType::Issue, $issueQuantity, [
                'source_location_id' => $locationId,
                'remarks' => $this->movementRemark('Imported monthly issued total.', $sheetRemarks),
            ]);
        }

        $add(
            InventoryTransactionType::MaterialReturn,
            $this->decimal($row['material_return']),
            ['remarks' => $this->movementRemark('Imported monthly material return total.', $sheetRemarks)]
        );

        $add(
            InventoryTransactionType::PhysicalAdjustment,
            $this->decimal($row['physical_adjustment']),
            ['remarks' => $this->movementRemark('Imported monthly physical adjustment total.', $sheetRemarks)]
        );

        $add(
            InventoryTransactionType::OtherMisc,
            $this->decimal($row['other_misc']),
            ['remarks' => $this->movementRemark('Imported monthly other misc total.', $sheetRemarks)]
        );

        return $movements;
    }

    private function movementRemark(string $prefix, ?string $sheetRemarks): string
    {
        return $sheetRemarks ? "{$prefix} Source note: {$sheetRemarks}" : $prefix;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::snake(trim($header));
    }

    private function decimal(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $clean = str_replace([',', ' '], '', trim((string) $value));

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean === '' ? null : $clean;
    }

    private function booleanOrDefault(mixed $value, bool $default): bool
    {
        $clean = Str::lower(trim((string) $value));

        return match ($clean) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            '' => $default,
            default => $default,
        };
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
