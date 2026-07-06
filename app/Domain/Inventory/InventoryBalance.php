<?php

namespace App\Domain\Inventory;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;

class InventoryBalance
{
    public static function currentQuantity(InventoryItem $item): float
    {
        return (float) $item->transactions()
            ->get()
            ->sum(fn (InventoryTransaction $transaction) => self::signedQuantity($transaction));
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
}
