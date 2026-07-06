<?php

namespace App\Actions\Inventory;

use App\Domain\Inventory\InventoryBalance;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInventoryTransactionAction
{
    public function execute(InventoryItem $item, array $validated, int $userId): InventoryTransaction
    {
        return DB::transaction(fn () => $this->persist($item, $validated, $userId));
    }

    public function persist(InventoryItem $item, array $validated, int $userId): InventoryTransaction
    {
        $transactionType = InventoryTransactionType::from($validated['transaction_type']);
        $quantity = (float) ($validated['quantity'] ?? 0);
        $unitCost = (float) ($validated['unit_cost'] ?? 0);
        $signedQuantity = match ($transactionType) {
            InventoryTransactionType::Opening,
            InventoryTransactionType::Receive,
            InventoryTransactionType::MaterialReturn => $quantity,
            InventoryTransactionType::Issue => $quantity * -1,
            InventoryTransactionType::InterlocTransfer => 0.0,
            InventoryTransactionType::PhysicalAdjustment,
            InventoryTransactionType::OtherMisc => $quantity,
            InventoryTransactionType::PriceAdjustment => 0.0,
        };

        $currentBalance = (float) $item->opening_stock + InventoryBalance::currentQuantity($item);
        if ($signedQuantity < 0 && ($currentBalance + $signedQuantity) < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This transaction would reduce stock below zero.',
            ]);
        }

        $totalValue = $transactionType === InventoryTransactionType::PriceAdjustment
            ? $unitCost
            : $quantity * $unitCost;

        return $item->transactions()->create([
            ...$validated,
            'total_value' => $totalValue,
            'created_by' => $userId,
        ])->load(['item', 'location', 'sourceLocation', 'destinationLocation', 'creator', 'cog']);
    }
}
