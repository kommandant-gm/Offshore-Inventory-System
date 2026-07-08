<?php

namespace App\Support;

use App\Domain\Inventory\InventoryBalance;
use App\Models\InventoryItem;

class InventoryItemProjector
{
    public function currentStock(InventoryItem $item): float
    {
        return round(InventoryBalance::currentQuantity($item), 2);
    }

    public function currentLocation(InventoryItem $item): ?string
    {
        return InventoryBalance::currentLocationLabel($item) ?? $item->defaultLocation?->name;
    }

    public function basePayload(InventoryItem $item, array $extra = []): array
    {
        return array_merge([
            'id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'category' => $item->category?->name,
            'href' => route('assets.show', $item),
        ], $extra);
    }

    public function listPayload(InventoryItem $item, array $extra = []): array
    {
        return array_merge([
            'id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'category' => $item->category?->name,
            'uom' => $item->uom,
            'location' => $this->currentLocation($item),
            'opening_stock' => $item->opening_stock,
            'current_stock' => $this->currentStock($item),
            'standard_cost' => $item->standard_cost,
            'minimum_stock' => $item->minimum_stock,
            'rack_no' => $item->rack_no,
            'active' => $item->active,
        ], $extra);
    }

    public function locationBalancePayload(InventoryItem $item): array
    {
        return $item->locationBalances
            ->filter(fn ($balance) => (float) $balance->quantity !== 0.0)
            ->map(fn ($balance) => [
                'location' => $balance->location?->name,
                'quantity' => round((float) $balance->quantity, 2),
            ])
            ->values()
            ->all();
    }
}
