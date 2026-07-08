<?php

namespace App\Domain\Inventory;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Collection;

class InventoryBalance
{
    public static function currentQuantity(InventoryItem $item): float
    {
        if ($item->relationLoaded('locationBalances') && $item->locationBalances->isNotEmpty()) {
            return (float) $item->locationBalances->sum('quantity');
        }

        $transactions = $item->relationLoaded('transactions')
            ? $item->transactions
            : $item->transactions()->get();

        return (float) ((float) $item->opening_stock + $transactions
            ->sum(fn (InventoryTransaction $transaction) => self::signedQuantity($transaction)));
    }

    public static function signedQuantity(InventoryTransaction $transaction): float
    {
        return match ($transaction->transaction_type) {
            InventoryTransactionType::Opening,
            InventoryTransactionType::Receive,
            InventoryTransactionType::MaterialReturn => (float) $transaction->quantity,
            InventoryTransactionType::Issue => (float) $transaction->quantity * -1,
            InventoryTransactionType::InterlocTransfer => 0.0,
            InventoryTransactionType::PhysicalAdjustment,
            InventoryTransactionType::OtherMisc => (float) $transaction->quantity,
            InventoryTransactionType::PriceAdjustment => 0.0,
        };
    }

    public static function positiveLocationNames(InventoryItem $item): Collection
    {
        $item->loadMissing('locationBalances.location', 'defaultLocation');

        $names = $item->locationBalances
            ->filter(fn ($balance) => (float) $balance->quantity > 0)
            ->map(fn ($balance) => $balance->location?->name)
            ->filter()
            ->values();

        if ($names->isEmpty() && (float) $item->opening_stock > 0 && $item->defaultLocation?->name) {
            return collect([$item->defaultLocation->name]);
        }

        return $names;
    }

    public static function currentLocationLabel(InventoryItem $item): ?string
    {
        $names = self::positiveLocationNames($item);

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->implode(', ');
    }
}
