<?php

namespace App\Support;

use App\Domain\Inventory\InventoryBalance;
use App\Models\Category;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InventoryAssistant
{
    public function __construct(
        private readonly StockAnomalyAgent $stockAnomalyAgent,
    ) {
    }

    public function respond(string $message, ?User $user = null): array
    {
        $question = trim($message);

        if ($question === '') {
            return [
                'intent' => 'unknown',
                'answer' => 'Ask about item location, current stock, last movement, or stock anomalies.',
            ];
        }

        $intent = $this->detectIntent($question);

        if (in_array($intent, ['anomalies', 'critical_anomalies', 'item_flagged'], true)) {
            if (! $user?->canRead('anomalies')) {
                return [
                    'intent' => $intent,
                    'answer' => 'You do not have permission to view stock anomalies.',
                ];
            }

            return $this->anomalyResponse($question, $intent);
        }

        if (in_array($intent, ['count_items', 'count_stock'], true)) {
            return $this->countResponse($question, $intent);
        }

        if ($intent === 'location_items') {
            return $this->locationItemsResponse($question);
        }

        $item = $this->findItem($question);

        if (! $item) {
            return [
                'intent' => $intent,
                'answer' => 'I could not find a matching stock item. Try the exact item code or a clearer description.',
            ];
        }

        return match ($intent) {
            'location' => $this->locationResponse($item),
            'stock' => $this->stockResponse($item),
            'movement' => $this->movementResponse($item),
            default => $this->summaryResponse($item),
        };
    }

    private function detectIntent(string $message): string
    {
        $value = Str::lower($message);

        if (Str::contains($value, ['why is']) && Str::contains($value, ['flagged'])) {
            return 'item_flagged';
        }

        if (Str::contains($value, ['critical anomalies', 'critical anomaly', 'critical stock issues'])) {
            return 'critical_anomalies';
        }

        if (Str::contains($value, ['show anomalies', 'stock anomalies', 'stock anomaly', 'anomalies for', 'anomaly for'])) {
            return 'anomalies';
        }

        if (Str::contains($value, ['how many items', 'count items', 'number of items'])) {
            return 'count_items';
        }

        if (Str::contains($value, ['total stock', 'total quantity', 'stock total', 'sum of stock'])) {
            return 'count_stock';
        }

        if (Str::contains($value, ['which items are in', 'what items are in', 'items in', 'stock in', 'items at', 'stored in'])) {
            return 'location_items';
        }

        if (Str::contains($value, ['where', 'location', 'located', 'store at', 'kept'])) {
            if (Str::contains($value, ['which items', 'what items', 'items in', 'stock in', 'items at', 'stored in'])) {
                return 'location_items';
            }

            return 'location';
        }

        if (Str::contains($value, ['stock', 'quantity', 'balance', 'how many', 'available'])) {
            if (Str::contains($value, ['which items', 'what items', 'items in', 'stock in', 'items at'])) {
                return 'location_items';
            }

            return 'stock';
        }

        if (Str::contains($value, ['last movement', 'latest movement', 'recent movement', 'last transaction', 'moved'])) {
            return 'movement';
        }

        return 'summary';
    }

    private function findItem(string $message): ?InventoryItem
    {
        $search = $this->extractSearchTerm($message);

        if ($search === '') {
            return null;
        }

        $normalized = Str::lower($search);

        $items = InventoryItem::query()
            ->with([
                'defaultLocation',
                'category',
                'transactions.location',
                'transactions.sourceLocation',
                'transactions.destinationLocation',
            ])
            ->where(function ($query) use ($search) {
                $query
                    ->where('item_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('item_code')
            ->limit(10)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return $items
            ->sortByDesc(function (InventoryItem $item) use ($normalized) {
                $itemCode = Str::lower($item->item_code);
                $description = Str::lower($item->description);

                return match (true) {
                    $itemCode === $normalized => 100,
                    $description === $normalized => 95,
                    Str::startsWith($itemCode, $normalized) => 80,
                    Str::startsWith($description, $normalized) => 70,
                    Str::contains($itemCode, $normalized) => 60,
                    default => 50,
                };
            })
            ->first();
    }

    private function extractSearchTerm(string $message): string
    {
        $value = trim($message);

        if (preg_match('/["\']([^"\']+)["\']/', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        $normalized = preg_replace('/\s+/', ' ', Str::lower($value)) ?? '';
        $patterns = [
            '/^where\s+is\s+/',
            '/^where\s+is\s+the\s+/',
            '/^what\s+is\s+the\s+current\s+location\s+of\s+/',
            '/^what\s+is\s+the\s+location\s+of\s+/',
            '/^location\s+of\s+/',
            '/^current\s+location\s+of\s+/',
            '/^stock\s+for\s+/',
            '/^current\s+stock\s+for\s+/',
            '/^what\s+is\s+the\s+current\s+stock\s+for\s+/',
            '/^what\s+is\s+the\s+stock\s+for\s+/',
            '/^how\s+many\s+.*\s+for\s+/',
            '/^last\s+movement\s+for\s+/',
            '/^latest\s+movement\s+for\s+/',
            '/^show\s+last\s+movement\s+for\s+/',
            '/^show\s+me\s+the\s+last\s+movement\s+for\s+/',
            '/^why\s+is\s+/',
            '/^tell\s+me\s+about\s+/',
            '/^item\s+/',
        ];

        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $normalized, 1);

            if ($candidate !== null && $candidate !== $normalized) {
                $normalized = $candidate;
                break;
            }
        }

        $normalized = trim(preg_replace('/[?.,!]+$/', '', $normalized) ?? '');
        $normalized = trim(preg_replace('/\s+flagged$/', '', $normalized) ?? '');

        return Str::upper($normalized) === $normalized ? $normalized : $normalized;
    }

    private function anomalyResponse(string $message, string $intent): array
    {
        $report = collect($this->stockAnomalyAgent->report()['entries']);
        $filters = $this->extractAnomalyFilters($message);

        if ($intent === 'critical_anomalies') {
            $report = $report->where('severity', 'critical');
        }

        if ($filters['category']) {
            $report = $report->filter(fn (array $entry) => Str::lower((string) ($entry['item']['category'] ?? '')) === Str::lower($filters['category']->name));
        }

        if ($filters['location']) {
            $report = $report->filter(function (array $entry) use ($filters) {
                $currentLocation = Str::lower((string) ($entry['current_location'] ?? ''));
                $assignedLocation = Str::lower((string) ($entry['assigned_location'] ?? ''));
                $needle = Str::lower($filters['location']->name);

                return $currentLocation === $needle || $assignedLocation === $needle;
            });
        }

        if ($filters['item']) {
            $report = $report->filter(fn (array $entry) => (int) $entry['item']['id'] === $filters['item']->id);
        }

        $report = $report->values();

        if ($intent === 'item_flagged') {
            return $this->itemFlaggedResponse($filters['item'], $report);
        }

        if ($report->isEmpty()) {
            $scope = $this->anomalyScopeLabel($filters['category'], $filters['location'], $filters['item']);
            $prefix = $intent === 'critical_anomalies' ? 'I did not find any critical anomalies' : 'I did not find any anomalies';

            return [
                'intent' => $intent,
                'answer' => "{$prefix} for {$scope}.",
                'items' => [],
            ];
        }

        $summary = $report->take(5)->map(function (array $entry) {
            return "{$entry['item']['item_code']} ({$entry['title']}, {$entry['severity']})";
        })->implode(', ');

        $scope = $this->anomalyScopeLabel($filters['category'], $filters['location'], $filters['item']);
        $criticalCount = $report->where('severity', 'critical')->count();
        $warningCount = $report->where('severity', 'warning')->count();
        $mode = $intent === 'critical_anomalies' ? 'critical anomalies' : 'anomalies';

        return [
            'intent' => $intent,
            'answer' => "I found {$report->count()} {$mode} for {$scope}. Critical: {$criticalCount}. Warning: {$warningCount}. Top matches: {$summary}.",
            'items' => $report->take(8)->map(fn (array $entry) => $this->anomalyItemPayload($entry))->all(),
        ];
    }

    private function itemFlaggedResponse(?InventoryItem $item, Collection $entries): array
    {
        if (! $item) {
            return [
                'intent' => 'item_flagged',
                'answer' => 'I could not tell which item you want checked. Try the exact item code, for example: Why is CON-Y1-0001 flagged?',
            ];
        }

        if ($entries->isEmpty()) {
            return [
                'intent' => 'item_flagged',
                'answer' => "Item {$item->item_code} is not currently flagged by the anomaly agent.",
                'item' => $this->itemPayload($item),
            ];
        }

        $reasons = $entries->map(function (array $entry) {
            return "{$entry['title']}: {$entry['detail']}";
        })->implode(' ');

        $actions = $entries->map(fn (array $entry) => $entry['recommendation'])->unique()->implode(' ');

        return [
            'intent' => 'item_flagged',
            'answer' => "Item {$item->item_code} is flagged for {$entries->count()} reason(s). {$reasons} Recommended action: {$actions}",
            'item' => $this->anomalyItemPayload($entries->first()),
            'items' => $entries->map(fn (array $entry) => $this->anomalyItemPayload($entry))->all(),
        ];
    }

    private function extractAnomalyFilters(string $message): array
    {
        $item = null;
        $category = $this->findCategory($message);
        $location = $this->findLocation($message);

        if (! $category && ! $location) {
            $item = $this->findItem($this->extractAnomalyItemSearch($message));
        }

        return [
            'category' => $category,
            'location' => $location,
            'item' => $item,
        ];
    }

    private function extractAnomalyItemSearch(string $message): string
    {
        $normalized = trim(Str::lower($message));
        $normalized = preg_replace('/^show\s+critical\s+anomal(?:y|ies)(?:\s+for)?\s+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^show\s+anomal(?:y|ies)(?:\s+for)?\s+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^stock\s+anomal(?:y|ies)(?:\s+for)?\s+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^why\s+is\s+(?:this\s+item\s+)?/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+flagged[?.,!]*$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^item\s+/', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function anomalyScopeLabel(?Category $category, ?Location $location, ?InventoryItem $item): string
    {
        return match (true) {
            $item !== null => $item->item_code,
            $category && $location => "{$category->name} at {$location->name}",
            $category !== null => $category->name,
            $location !== null => $location->name,
            default => 'all mapped inventory',
        };
    }

    private function anomalyItemPayload(array $entry): array
    {
        return [
            'id' => $entry['item']['id'],
            'item_code' => $entry['item']['item_code'],
            'description' => $entry['item']['description']." - {$entry['title']} ({$entry['severity']})",
            'href' => $entry['item']['href'],
            'current_stock' => $entry['current_stock'],
            'uom' => $entry['uom'],
            'current_location' => $entry['current_location'] ?? $entry['assigned_location'] ?? 'Not mapped',
        ];
    }

    private function locationResponse(InventoryItem $item): array
    {
        $movement = $this->latestMovement($item);
        $location = $this->currentLocationLabel($item, $movement);
        $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);

        $answer = $location
            ? "Item {$item->item_code} is currently at {$location}."
            : "Item {$item->item_code} does not have a current location recorded yet.";

        $answer .= " Current stock is {$this->formatNumber($currentStock)} {$item->uom}.";

        if ($movement) {
            $answer .= ' Last movement was '.Str::of($movement->transaction_type->value)->replace('_', ' ')->title().' on '.$movement->transaction_date->format('Y-m-d').'.';
        }

        return [
            'intent' => 'location',
            'answer' => $answer,
            'item' => $this->itemPayload($item),
        ];
    }

    private function stockResponse(InventoryItem $item): array
    {
        $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);
        $movement = $this->latestMovement($item);
        $location = $this->currentLocationLabel($item, $movement) ?? 'location pending';

        return [
            'intent' => 'stock',
            'answer' => "Item {$item->item_code} has current stock of {$this->formatNumber($currentStock)} {$item->uom}. Current location is {$location}. Minimum stock is {$this->formatNumber($item->minimum_stock)} {$item->uom}.",
            'item' => $this->itemPayload($item),
        ];
    }

    private function movementResponse(InventoryItem $item): array
    {
        $movement = $this->latestMovement($item);

        if (! $movement) {
            return [
                'intent' => 'movement',
                'answer' => "Item {$item->item_code} does not have any movement recorded yet.",
                'item' => $this->itemPayload($item),
            ];
        }

        $type = Str::of($movement->transaction_type->value)->replace('_', ' ')->title();
        $source = $movement->sourceLocation?->name ?? $movement->location?->name ?? 'source pending';
        $destination = $movement->destinationLocation?->name ?? $movement->location?->name ?? 'destination pending';

        return [
            'intent' => 'movement',
            'answer' => "Last movement for {$item->item_code} was {$type} on {$movement->transaction_date->format('Y-m-d')} from {$source} to {$destination}. Quantity: {$this->formatNumber($movement->quantity)} {$item->uom}.",
            'item' => $this->itemPayload($item),
        ];
    }

    private function summaryResponse(InventoryItem $item): array
    {
        $movement = $this->latestMovement($item);
        $location = $this->currentLocationLabel($item, $movement) ?? 'location pending';
        $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);

        return [
            'intent' => 'summary',
            'answer' => "Item {$item->item_code} is currently at {$location} with stock {$this->formatNumber($currentStock)} {$item->uom}. Ask for location, stock, or last movement if you need a specific answer.",
            'item' => $this->itemPayload($item),
        ];
    }

    private function locationItemsResponse(string $message): array
    {
        $location = $this->findLocation($message);

        if (! $location) {
            return [
                'intent' => 'location_items',
                'answer' => 'I could not find that location. Try the exact location code or name.',
            ];
        }

        $items = InventoryItem::query()
            ->with([
                'category',
                'defaultLocation',
                'transactions.location',
                'transactions.sourceLocation',
                'transactions.destinationLocation',
            ])
            ->orderBy('item_code')
            ->get()
            ->map(function (InventoryItem $item) {
                $movement = $this->latestMovement($item);
                $currentLocation = $this->currentLocationLabel($item, $movement);
                $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);

                return [
                    'item' => $item,
                    'current_location' => $currentLocation,
                    'current_stock' => $currentStock,
                ];
            })
            ->filter(function (array $entry) use ($location) {
                return Str::lower((string) $entry['current_location']) === Str::lower($location->name);
            })
            ->take(8)
            ->values();

        if ($items->isEmpty()) {
            return [
                'intent' => 'location_items',
                'answer' => "I did not find any items currently mapped to {$location->name}.",
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                ],
            ];
        }

        $lines = $items->map(function (array $entry) {
            /** @var InventoryItem $item */
            $item = $entry['item'];

            return "{$item->item_code} ({$this->formatNumber($entry['current_stock'])} {$item->uom})";
        })->implode(', ');

        return [
            'intent' => 'location_items',
            'answer' => "Items currently mapped to {$location->name}: {$lines}.",
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'items' => $items->map(fn (array $entry) => $this->itemPayload($entry['item'], [
                'current_location' => $entry['current_location'],
                'current_stock' => $this->formatNumber($entry['current_stock']),
                'uom' => $entry['item']->uom,
            ]))->all(),
        ];
    }

    private function countResponse(string $message, string $intent): array
    {
        $filters = $this->extractCountFilters($message);
        $items = $this->mappedItems();

        if ($filters['category']) {
            $items = $items->filter(fn (array $entry) => $entry['category'] && Str::lower($entry['category']) === Str::lower($filters['category']->name));
        }

        if ($filters['location']) {
            $items = $items->filter(fn (array $entry) => $entry['current_location'] && Str::lower($entry['current_location']) === Str::lower($filters['location']->name));
        }

        $itemCount = $items->count();
        $stockTotal = $items->sum(fn (array $entry) => $entry['current_stock']);

        if ($intent === 'count_stock') {
            $scope = $this->countScopeLabel($filters['category'], $filters['location']);

            return [
                'intent' => 'count_stock',
                'answer' => "Total stock for {$scope} is {$this->formatNumber($stockTotal)} units across {$itemCount} items.",
            ];
        }

        $scope = $this->countScopeLabel($filters['category'], $filters['location']);

        return [
            'intent' => 'count_items',
            'answer' => "There are {$itemCount} items in {$scope}. Total stock across those items is {$this->formatNumber($stockTotal)} units.",
        ];
    }

    private function latestMovement(InventoryItem $item): ?InventoryTransaction
    {
        if ($item->relationLoaded('transactions')) {
            return $item->transactions
                ->sortByDesc(fn (InventoryTransaction $transaction) => sprintf(
                    '%s-%010d',
                    $transaction->transaction_date?->format('Ymd') ?? '00000000',
                    $transaction->id
                ))
                ->first();
        }

        return $item->transactions()
            ->with(['location', 'sourceLocation', 'destinationLocation'])
            ->latest('transaction_date')
            ->latest('id')
            ->first();
    }

    private function currentLocationLabel(InventoryItem $item, ?InventoryTransaction $movement): ?string
    {
        if (! $movement) {
            return $item->defaultLocation?->name;
        }

        return match ($movement->transaction_type) {
            InventoryTransactionType::Issue,
            InventoryTransactionType::InterlocTransfer => $movement->destinationLocation?->name ?? $movement->location?->name ?? $item->defaultLocation?->name,
            InventoryTransactionType::Receive,
            InventoryTransactionType::Opening,
            InventoryTransactionType::MaterialReturn,
            InventoryTransactionType::PhysicalAdjustment,
            InventoryTransactionType::OtherMisc,
            InventoryTransactionType::PriceAdjustment => $movement->location?->name ?? $movement->destinationLocation?->name ?? $item->defaultLocation?->name,
        };
    }

    private function mappedItems(): Collection
    {
        return InventoryItem::query()
            ->with([
                'category',
                'defaultLocation',
                'transactions.location',
                'transactions.sourceLocation',
                'transactions.destinationLocation',
            ])
            ->orderBy('item_code')
            ->get()
            ->map(function (InventoryItem $item) {
                $movement = $this->latestMovement($item);
                $currentLocation = $this->currentLocationLabel($item, $movement);
                $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);

                return [
                    'item' => $item,
                    'category' => $item->category?->name,
                    'current_location' => $currentLocation,
                    'current_stock' => $currentStock,
                ];
            });
    }

    private function extractCountFilters(string $message): array
    {
        return [
            'category' => $this->findCategory($message),
            'location' => $this->findLocation($message),
        ];
    }

    private function findCategory(string $message): ?Category
    {
        $normalized = Str::lower(trim(preg_replace('/[?.,!]+$/', '', $message) ?? ''));

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return null;
        }

        return $categories
            ->filter(function (Category $category) use ($normalized) {
                $name = Str::lower($category->name);
                $code = Str::lower($category->code);

                return Str::contains($normalized, $name) || ($code !== '' && Str::contains($normalized, $code));
            })
            ->sortByDesc(function (Category $category) use ($normalized) {
                $name = Str::lower($category->name);
                $code = Str::lower($category->code);

                return match (true) {
                    $name === $normalized => 100,
                    Str::contains($normalized, $name) => 90,
                    $code !== '' && Str::contains($normalized, $code) => 80,
                    default => 50,
                };
            })
            ->first();
    }

    private function findLocation(string $message): ?Location
    {
        $normalized = Str::lower(trim(preg_replace('/[?.,!]+$/', '', $message) ?? ''));
        $patterns = [
            '/^show\s+critical\s+anomal(?:y|ies)(?:\s+for)?\s+/',
            '/^show\s+anomal(?:y|ies)(?:\s+for)?\s+/',
            '/^stock\s+anomal(?:y|ies)(?:\s+for)?\s+/',
            '/^which\s+items\s+are\s+in\s+/',
            '/^what\s+items\s+are\s+in\s+/',
            '/^items\s+in\s+/',
            '/^items\s+at\s+/',
            '/^stock\s+in\s+/',
            '/^which\s+stock\s+is\s+in\s+/',
            '/^how\s+many\s+items\s+are\s+in\s+/',
            '/^count\s+items\s+in\s+/',
            '/^number\s+of\s+items\s+in\s+/',
            '/^total\s+stock\s+in\s+/',
        ];

        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $normalized, 1);

            if ($candidate !== null && $candidate !== $normalized) {
                $normalized = trim($candidate);
                break;
            }
        }

        if ($normalized === '') {
            return null;
        }

        $locations = Location::query()
            ->where('name', 'like', "%{$normalized}%")
            ->orWhere('code', 'like', "%{$normalized}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($locations->isEmpty()) {
            return null;
        }

        return $locations
            ->sortByDesc(function (Location $location) use ($normalized) {
                $name = Str::lower($location->name);
                $code = Str::lower($location->code);

                return match (true) {
                    $name === $normalized => 100,
                    $code === $normalized => 95,
                    Str::startsWith($name, $normalized) => 80,
                    Str::contains($name, $normalized) => 70,
                    Str::contains($code, $normalized) => 60,
                    default => 50,
                };
            })
            ->first();
    }

    private function countScopeLabel(?Category $category, ?Location $location): string
    {
        return match (true) {
            $category && $location => "{$category->name} at {$location->name}",
            $category !== null => $category->name,
            $location !== null => $location->name,
            default => 'all mapped inventory',
        };
    }

    private function itemPayload(InventoryItem $item, array $extra = []): array
    {
        return array_merge([
            'id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'category' => $item->category?->name,
            'href' => route('assets.show', $item),
        ], $extra);
    }

    private function formatNumber(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
