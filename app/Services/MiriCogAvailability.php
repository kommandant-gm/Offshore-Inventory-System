<?php

namespace App\Services;

use App\Models\MiriCogItem;
use Illuminate\Validation\ValidationException;

class MiriCogAvailability
{
    public const EQUIPMENT = ['Major equipment', 'Rental'];
    public const OUTBOUND = ['Issue out', 'Transfer', 'Return to supplier'];

    // Replay saved movements, not document dates: backdating must not bypass a reservation.
    public function outstanding(int $branch, string $type, array $ids): array
    {
        if (! in_array($type, self::EQUIPMENT, true) || ! $ids) return [];

        $lines = MiriCogItem::query()->where('branch_id', $branch)->where('item_type', $type)
            ->whereIn('item_id', $ids)->whereHas('cog', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with('cog')->orderBy('miri_cog_id')->orderBy('id')->get();
        $state = [];
        foreach ($lines as $line) {
            $id = $line->item_id;
            $quantity = (int) round((float) $line->quantity * 1000);
            if (in_array($line->cog->movement_type, self::OUTBOUND, true)) {
                $state[$id][] = ['cog_no' => $line->cog->display_cog_no, 'quantity' => $quantity];
            } elseif ($line->cog->movement_type === 'Received backload') {
                foreach ($state[$id] ?? [] as $i => $allocation) {
                    $returned = min($quantity, $allocation['quantity']);
                    $state[$id][$i]['quantity'] -= $returned;
                    $quantity -= $returned;
                }
            }
        }
        foreach ($state as $id => $allocations) {
            $state[$id] = array_values(array_filter($allocations, fn ($a) => $a['quantity'] > 0));
        }
        return $state;
    }

    public function validate(int $branch, string $movement, array $lines): void
    {
        $seen = [];
        foreach ($lines as $i => $line) {
            if (! in_array($line['item_type'], self::EQUIPMENT, true)) continue;
            $key = $line['item_type'].':'.$line['item_id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["items.$i.item_id" => 'This equipment is already included in this note.']);
            }
            $seen[$key] = true;
            $allocations = $this->outstanding($branch, $line['item_type'], [$line['item_id']])[$line['item_id']] ?? [];
            if (in_array($movement, self::OUTBOUND, true) && $allocations) {
                $numbers = implode(', ', array_unique(array_column($allocations, 'cog_no')));
                throw ValidationException::withMessages(["items.$i.item_id" => "Equipment is already allocated to $numbers. Record its return/backload before issuing again."]);
            }
            if ($movement === 'Received backload' && (int) round((float) $line['quantity'] * 1000) > array_sum(array_column($allocations, 'quantity'))) {
                throw ValidationException::withMessages(["items.$i.quantity" => 'Return quantity exceeds the equipment quantity outstanding on active COG notes.']);
            }
        }
    }
}
