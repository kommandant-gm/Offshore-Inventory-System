<?php
namespace App\Services;

use App\Models\{Branch, MiriCogItem, MiriPaintItem};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaintStockLedger
{
    public function period(): string { return now('Asia/Kuala_Lumpur')->startOfMonth()->toDateString(); }
    private function milli($value): int { return (int) round((float) $value * 1000); }
    private function decimal(int $value): string { return number_format($value / 1000, 3, '.', ''); }

    // Caller owns the row lock, inside a transaction. Closed months are immutable.
    public function rollItem(MiriPaintItem $item): void
    {
        $target = $this->period();
        $period = $item->stock_period ?: $target;
        while ($period < $target) {
            DB::table('miri_paint_stock_months')->insert([
                'branch_id' => $item->branch_id, 'paint_item_id' => $item->id, 'period' => $period,
                'opening_cans' => $item->opening_cans, 'opening_litres' => $item->opening_litres,
                'closing_cans' => $item->balance_cans, 'closing_litres' => $item->balance_litres, 'created_at' => now(),
            ]);
            $item->opening_cans = $item->balance_cans;
            $item->opening_litres = $item->balance_litres;
            $period = CarbonImmutable::parse($period)->addMonth()->toDateString();
        }
        $item->stock_period = $period;
        if ($item->isDirty()) $item->save();
    }

    public function rollover(?int $branch = null): void
    {
        $ids = MiriPaintItem::withoutGlobalScopes()->when($branch !== null, fn ($q) => $q->where('branch_id', $branch))
            ->where(fn ($q) => $q->whereNull('stock_period')->orWhere('stock_period', '<', $this->period()))->orderBy('id')->pluck('id');
        foreach ($ids as $id) DB::transaction(function () use ($id) {
            $item = MiriPaintItem::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->rollItem($item);
        });
    }

    public function post(MiriCogItem $line, int $userId, bool $reverse = false): void
    {
        DB::transaction(function () use ($line, $userId, $reverse) {
            Branch::whereKey($line->branch_id)->lockForUpdate()->firstOrFail();
            $this->postLocked($line, $userId, $reverse);
        });
    }

    private function postLocked(MiriCogItem $line, int $userId, bool $reverse): void
    {
        if ($line->item_type !== 'Paint') return;
        $movement = $line->cog->movement_type;
        $kind = $reverse ? 'cancellation' : ($movement === 'Received backload' ? 'backload' : 'outbound');
        if (DB::table('miri_paint_stock_movements')->where('cog_item_id', $line->id)->where('kind', $kind)->exists()) return;
        $original = DB::table('miri_paint_stock_movements')->where('cog_item_id', $line->id)->where('kind', 'outbound')->first();
        if ($reverse && ! $original) return; // Never reverse legacy document-only COGs.
        $item = MiriPaintItem::withoutGlobalScopes()->where('branch_id', $line->branch_id)->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
        $this->rollItem($item);
        $unit = $reverse ? $original->unit : $line->unit;
        $field = $unit === 'LTR' ? 'balance_litres' : 'balance_cans';
        $before = $item->$field;
        $fail = fn ($message) => throw ValidationException::withMessages(['items' => "Paint #{$item->id}: {$message}"]);
        if ($before === null) $fail("record the {$unit} balance before posting a stock movement.");
        $quantity = $reverse ? -$this->milli($original->quantity) : $this->milli($line->quantity) * ($kind === 'outbound' ? -1 : 1);
        if ($kind === 'backload') {
            $net = DB::table('miri_paint_stock_movements')->where('paint_item_id', $item->id)->where('unit', $unit)
                ->whereIn('kind', ['outbound', 'backload', 'cancellation'])->sum('quantity');
            if ($quantity > -$this->milli($net)) $fail('backload exceeds tracked outbound quantity in this unit. Record legacy returns as a reviewed stock adjustment.');
        }
        $after = $this->milli($before) + $quantity;
        if ($after < 0) $fail("insufficient {$unit} stock (available: {$before}).");
        if ($after > 99999999999999) $fail('stock exceeds the supported quantity range.');
        $item->$field = $this->decimal($after);
        $item->save();
        DB::table('miri_paint_stock_movements')->insert([
            'branch_id' => $item->branch_id, 'paint_item_id' => $item->id, 'cog_item_id' => $line->id,
            'kind' => $kind, 'unit' => $unit, 'quantity' => $this->decimal($quantity),
            'balance_before' => $before, 'balance_after' => $item->$field, 'period' => $item->stock_period,
            'user_id' => $userId, 'note' => $line->cog->cog_no.' / '.$movement, 'created_at' => now(),
        ]);
    }

    public function token(MiriPaintItem $item): string
    {
        return hash('sha256', json_encode([$item->stock_period, $item->opening_cans, $item->opening_litres, $item->balance_cans, $item->balance_litres]));
    }

    public function adjustment(MiriPaintItem $item, array $before, int $userId, ?string $note): void
    {
        foreach (['CAN' => 'balance_cans', 'LTR' => 'balance_litres'] as $unit => $field) {
            $old = $before[$field] ?? null;
            $new = $item->$field;
            if ($old === $new) continue;
            DB::table('miri_paint_stock_movements')->insert([
                'branch_id' => $item->branch_id, 'paint_item_id' => $item->id, 'kind' => 'adjustment', 'unit' => $unit,
                'quantity' => $old === null || $new === null ? null : $this->decimal($this->milli($new) - $this->milli($old)),
                'balance_before' => $old, 'balance_after' => $new, 'period' => $item->stock_period,
                'user_id' => $userId, 'note' => $note, 'created_at' => now(),
            ]);
        }
    }
}
