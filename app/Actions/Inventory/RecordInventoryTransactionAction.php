<?php

namespace App\Actions\Inventory;

use App\Domain\Inventory\InventoryBalance;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryLocationBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInventoryTransactionAction
{
    public function __construct(
        private readonly InventoryLocationBalanceService $locationBalanceService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

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

        $item->loadMissing(['transactions', 'locationBalances']);
        $currentBalance = InventoryBalance::currentQuantity($item);
        if ($signedQuantity < 0 && ($currentBalance + $signedQuantity) < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This transaction would reduce stock below zero.',
            ]);
        }

        if (in_array($transactionType, [InventoryTransactionType::Issue, InventoryTransactionType::InterlocTransfer], true)) {
            $sourceLocationId = $validated['source_location_id'] ?? $validated['location_id'] ?? $item->default_location_id;
            $sourceBalance = $this->locationBalanceService->quantityAtLocation($item, $sourceLocationId);

            if ($sourceBalance < $quantity) {
                throw ValidationException::withMessages([
                    'source_location_id' => 'The selected source location does not have enough stock for this transaction.',
                ]);
            }
        }

        $totalValue = $transactionType === InventoryTransactionType::PriceAdjustment
            ? $unitCost
            : $quantity * $unitCost;

        $transaction = $item->transactions()->create([
            ...$validated,
            'total_value' => $totalValue,
            'created_by' => $userId,
        ])->load(['item', 'location', 'sourceLocation', 'destinationLocation', 'creator', 'cog']);

        $this->locationBalanceService->applyTransaction($item, $transaction);
        $this->auditLogger->record(
            module: 'movements',
            event: 'created',
            summary: "Recorded {$transaction->transaction_type->value} movement for {$item->item_code}.",
            auditable: $transaction,
            after: [
                'item_id' => $item->id,
                'transaction_type' => $transaction->transaction_type->value,
                'quantity' => $transaction->quantity,
                'location_id' => $transaction->location_id,
                'source_location_id' => $transaction->source_location_id,
                'destination_location_id' => $transaction->destination_location_id,
            ],
            user: User::query()->find($userId),
        );

        return $transaction;
    }
}
