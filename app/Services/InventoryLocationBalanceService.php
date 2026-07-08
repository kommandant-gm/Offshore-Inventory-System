<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Collection;

class InventoryLocationBalanceService
{
    public function initializeItem(InventoryItem $item): void
    {
        $item->loadMissing('locationBalances');

        if ($item->locationBalances->isNotEmpty()) {
            return;
        }

        $this->adjustLocationQuantity($item, $item->default_location_id, (float) $item->opening_stock);
    }

    public function syncItem(InventoryItem $item): void
    {
        $item->loadMissing(['transactions', 'locationBalances']);

        $balances = $this->calculateBalances($item);

        $item->locationBalances()->delete();

        foreach ($balances as $locationId => $quantity) {
            if (abs($quantity) < 0.00001) {
                continue;
            }

            $item->locationBalances()->create([
                'location_id' => $locationId,
                'quantity' => round($quantity, 2),
            ]);
        }
    }

    public function applyTransaction(InventoryItem $item, InventoryTransaction $transaction): void
    {
        $item->loadMissing('locationBalances');

        $quantity = (float) $transaction->quantity;

        match ($transaction->transaction_type) {
            InventoryTransactionType::Opening,
            InventoryTransactionType::Receive,
            InventoryTransactionType::MaterialReturn,
            InventoryTransactionType::PhysicalAdjustment,
            InventoryTransactionType::OtherMisc => $this->adjustLocationQuantity(
                $item,
                $transaction->location_id
                ?? $transaction->destination_location_id
                ?? $transaction->source_location_id
                ?? $item->default_location_id,
                $quantity,
            ),
            InventoryTransactionType::Issue => $this->adjustLocationQuantity(
                $item,
                $transaction->source_location_id
                ?? $transaction->location_id
                ?? $item->default_location_id,
                $quantity * -1,
            ),
            InventoryTransactionType::InterlocTransfer => $this->applyTransfer($item, $transaction, $quantity),
            InventoryTransactionType::PriceAdjustment => null,
        };
    }

    public function quantityAtLocation(InventoryItem $item, ?int $locationId): float
    {
        if (! $locationId) {
            return 0.0;
        }

        $item->loadMissing('locationBalances');

        return (float) ($item->locationBalances->firstWhere('location_id', $locationId)?->quantity ?? 0);
    }

    public function positiveLocationNames(InventoryItem $item): Collection
    {
        $item->loadMissing('locationBalances.location', 'defaultLocation');

        $names = $item->locationBalances
            ->filter(fn ($balance) => (float) $balance->quantity > 0)
            ->map(fn ($balance) => $balance->location?->name)
            ->filter()
            ->values();

        if ($names->isEmpty() && $item->defaultLocation?->name && (float) $item->opening_stock > 0) {
            return collect([$item->defaultLocation->name]);
        }

        return $names;
    }

    public function currentLocationLabel(InventoryItem $item): ?string
    {
        $names = $this->positiveLocationNames($item);

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->implode(', ');
    }

    /**
     * @return array<int, float>
     */
    private function calculateBalances(InventoryItem $item): array
    {
        $balances = [];

        $apply = static function (?int $locationId, float $quantity) use (&$balances): void {
            if (! $locationId || abs($quantity) < 0.00001) {
                return;
            }

            $balances[$locationId] = ($balances[$locationId] ?? 0.0) + $quantity;
        };

        $apply($item->default_location_id, (float) $item->opening_stock);

        foreach ($item->transactions->sortBy([
            ['transaction_date', 'asc'],
            ['id', 'asc'],
        ]) as $transaction) {
            $quantity = (float) $transaction->quantity;

            switch ($transaction->transaction_type) {
                case InventoryTransactionType::Opening:
                case InventoryTransactionType::Receive:
                case InventoryTransactionType::MaterialReturn:
                case InventoryTransactionType::PhysicalAdjustment:
                case InventoryTransactionType::OtherMisc:
                    $apply(
                        $transaction->location_id
                        ?? $transaction->destination_location_id
                        ?? $transaction->source_location_id
                        ?? $item->default_location_id,
                        $quantity,
                    );
                    break;

                case InventoryTransactionType::Issue:
                    $apply(
                        $transaction->source_location_id
                        ?? $transaction->location_id
                        ?? $item->default_location_id,
                        $quantity * -1,
                    );
                    break;

                case InventoryTransactionType::InterlocTransfer:
                    $apply($transaction->source_location_id ?? $transaction->location_id, $quantity * -1);
                    $apply($transaction->destination_location_id ?? $transaction->location_id, $quantity);
                    break;

                case InventoryTransactionType::PriceAdjustment:
                    break;
            }
        }

        return $balances;
    }

    private function applyTransfer(InventoryItem $item, InventoryTransaction $transaction, float $quantity): void
    {
        $this->adjustLocationQuantity(
            $item,
            $transaction->source_location_id ?? $transaction->location_id,
            $quantity * -1,
        );

        $this->adjustLocationQuantity(
            $item,
            $transaction->destination_location_id ?? $transaction->location_id,
            $quantity,
        );
    }

    private function adjustLocationQuantity(InventoryItem $item, ?int $locationId, float $delta): void
    {
        if (! $locationId || abs($delta) < 0.00001) {
            return;
        }

        $balance = $item->locationBalances()
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        $newQuantity = round(((float) $balance?->quantity) + $delta, 2);

        if (abs($newQuantity) < 0.00001) {
            $balance?->delete();
        } elseif ($balance) {
            $balance->update(['quantity' => $newQuantity]);
        } else {
            $item->locationBalances()->create([
                'location_id' => $locationId,
                'quantity' => $newQuantity,
            ]);
        }

        if ($item->relationLoaded('locationBalances')) {
            $item->unsetRelation('locationBalances');
        }
    }
}
