<?php

namespace App\Support;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StockAnomalyAgent
{
    private const STALE_DAYS = 90;

    public function __construct(
        private readonly InventoryItemProjector $itemProjector,
    ) {
    }

    public function report(): array
    {
        $generatedAt = now();

        $entries = InventoryItem::query()
            ->with([
                'category',
                'defaultLocation',
                'locationBalances.location',
                'latestTransaction.location',
                'latestTransaction.sourceLocation',
                'latestTransaction.destinationLocation',
            ])
            ->orderBy('item_code')
            ->get()
            ->flatMap(fn (InventoryItem $item) => $this->detectForItem($item, $generatedAt))
            ->values();

        return [
            'generated_at' => $generatedAt->format('Y-m-d H:i'),
            'summary' => [
                'total' => $entries->count(),
                'critical' => $entries->where('severity', 'critical')->count(),
                'warning' => $entries->where('severity', 'warning')->count(),
                'items_affected' => $entries->pluck('item.id')->unique()->count(),
            ],
            'groups' => $this->groupSummary($entries),
            'entries' => $entries->all(),
        ];
    }

    private function detectForItem(InventoryItem $item, Carbon $generatedAt): array
    {
        $currentStock = $this->itemProjector->currentStock($item);
        $latestMovement = $this->latestMovement($item);
        $currentLocation = $this->itemProjector->currentLocation($item);
        $defaultLocation = $item->defaultLocation?->name;
        $entries = [];

        if ($currentStock < 0) {
            $entries[] = $this->makeEntry(
                type: 'negative_stock',
                severity: 'critical',
                title: 'Negative stock balance',
                detail: "Current stock is {$this->formatNumber($currentStock)} {$item->uom}. This item has gone below zero and needs movement review.",
                item: $item,
                currentStock: $currentStock,
                currentLocation: $currentLocation,
                latestMovement: $latestMovement,
                recommendation: 'Review the latest issue, adjustment, or opening balance before more transactions are posted.'
            );
        }

        if ($item->minimum_stock !== null && $currentStock < (float) $item->minimum_stock) {
            $gap = round((float) $item->minimum_stock - $currentStock, 2);

            $entries[] = $this->makeEntry(
                type: 'below_minimum',
                severity: $currentStock <= 0 ? 'critical' : 'warning',
                title: 'Below minimum stock',
                detail: "Current stock is {$this->formatNumber($currentStock)} {$item->uom} against a minimum of {$this->formatNumber($item->minimum_stock)} {$item->uom}.",
                item: $item,
                currentStock: $currentStock,
                currentLocation: $currentLocation,
                latestMovement: $latestMovement,
                recommendation: "Replenish at least {$this->formatNumber($gap)} {$item->uom} or adjust the minimum threshold if it is outdated."
            );
        }

        if ($item->active && $currentStock > 0) {
            if (! $latestMovement) {
                $entries[] = $this->makeEntry(
                    type: 'never_moved',
                    severity: 'warning',
                    title: 'Stock with no movement history',
                    detail: "This active item still has {$this->formatNumber($currentStock)} {$item->uom}, but no movement has been recorded yet.",
                    item: $item,
                    currentStock: $currentStock,
                    currentLocation: $currentLocation,
                    latestMovement: null,
                    recommendation: 'Verify whether the opening stock is correct and whether receipts or issues are being posted consistently.'
                );
            } elseif ($latestMovement->transaction_date->diffInDays($generatedAt) >= self::STALE_DAYS) {
                $days = $latestMovement->transaction_date->diffInDays($generatedAt);

                $entries[] = $this->makeEntry(
                    type: 'stale_stock',
                    severity: 'warning',
                    title: 'No recent movement',
                    detail: "No movement has been recorded for {$days} days while {$this->formatNumber($currentStock)} {$item->uom} remains on hand.",
                    item: $item,
                    currentStock: $currentStock,
                    currentLocation: $currentLocation,
                    latestMovement: $latestMovement,
                    recommendation: 'Check whether this is dead stock, misplaced stock, or an item that should be marked inactive.'
                );
            }
        }

        if ($defaultLocation && $currentLocation && Str::lower($defaultLocation) !== Str::lower($currentLocation)) {
            $entries[] = $this->makeEntry(
                type: 'location_mismatch',
                severity: 'warning',
                title: 'Current location differs from assigned location',
                detail: "Assigned location is {$defaultLocation}, but the latest movement places the item at {$currentLocation}.",
                item: $item,
                currentStock: $currentStock,
                currentLocation: $currentLocation,
                latestMovement: $latestMovement,
                recommendation: 'Confirm the physical rack or bin, then either correct the movement trail or update the assigned default location.'
            );
        }

        if (! $currentLocation && $currentStock > 0) {
            $entries[] = $this->makeEntry(
                type: 'missing_location',
                severity: 'warning',
                title: 'Stock without mapped location',
                detail: "This item still has {$this->formatNumber($currentStock)} {$item->uom}, but no current location can be derived from the records.",
                item: $item,
                currentStock: $currentStock,
                currentLocation: null,
                latestMovement: $latestMovement,
                recommendation: 'Assign a default location or post a corrective movement so inventory users can locate the stock.'
            );
        }

        return $entries;
    }

    private function groupSummary(Collection $entries): array
    {
        $labels = [
            'negative_stock' => 'Negative stock',
            'below_minimum' => 'Below minimum',
            'never_moved' => 'No movement history',
            'stale_stock' => 'Stale stock',
            'location_mismatch' => 'Location mismatch',
            'missing_location' => 'Missing location',
        ];

        return collect($labels)
            ->map(function (string $label, string $type) use ($entries) {
                $matches = $entries->where('type', $type);

                return [
                    'type' => $type,
                    'label' => $label,
                    'count' => $matches->count(),
                    'critical' => $matches->where('severity', 'critical')->count(),
                ];
            })
            ->filter(fn (array $entry) => $entry['count'] > 0)
            ->values()
            ->all();
    }

    private function makeEntry(
        string $type,
        string $severity,
        string $title,
        string $detail,
        InventoryItem $item,
        float $currentStock,
        ?string $currentLocation,
        ?InventoryTransaction $latestMovement,
        string $recommendation
    ): array {
        return [
            'id' => "{$type}-{$item->id}",
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'recommendation' => $recommendation,
            'item' => [
                ...$this->itemProjector->basePayload($item),
            ],
            'current_stock' => $this->formatNumber($currentStock),
            'minimum_stock' => $item->minimum_stock !== null ? $this->formatNumber($item->minimum_stock) : null,
            'uom' => $item->uom,
            'current_location' => $currentLocation,
            'assigned_location' => $item->defaultLocation?->name,
            'last_movement' => $latestMovement ? [
                'date' => $latestMovement->transaction_date->format('Y-m-d'),
                'type' => Str::of($latestMovement->transaction_type->value)->replace('_', ' ')->title()->value(),
            ] : null,
        ];
    }

    private function latestMovement(InventoryItem $item): ?InventoryTransaction
    {
        if ($item->relationLoaded('latestTransaction')) {
            return $item->latestTransaction;
        }

        return $item->latestTransaction()
            ->with(['location', 'sourceLocation', 'destinationLocation'])
            ->first();
    }

    private function formatNumber(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
