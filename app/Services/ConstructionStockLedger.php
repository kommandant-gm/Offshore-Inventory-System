<?php
namespace App\Services;

use App\Models\{Branch, MiriCog, MiriConstructionItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionStockLedger
{
    private function fail(string $message): never { throw ValidationException::withMessages(['stock' => $message]); }
    private function milli($value): int { return (int) round((float) $value * 1000); }
    private function decimal(int $value): string { return number_format($value / 1000, 3, '.', ''); }
    public function unit($value): string { return strtoupper(trim((string) $value)); }
    public function token(MiriConstructionItem $item): string
    {
        return hash('sha256', json_encode([$item->stock_balance, $item->unit, $item->current_location, $item->stock_initialized_at, $item->stock_revision]));
    }
    private function ready(MiriConstructionItem $item): void
    {
        if (! $item->stock_initialized_at || $item->stock_balance === null || blank($item->unit) || blank($item->current_location)) {
            $this->fail("Record #{$item->id}: verify the opening balance, unit and location first.");
        }
    }
    private function post(MiriConstructionItem $item, string $kind, int $delta, int $user, string $key, string $note, ?string $reference = null, ?int $line = null, ?int $related = null, ?string $hash = null): void
    {
        $before = $item->stock_balance;
        $after = ($kind === 'opening' ? 0 : $this->milli($before)) + $delta;
        if ($after < 0) $this->fail("Record #{$item->id}: insufficient stock. Available: {$before} {$item->unit}.");
        if ($after > 99999999999999) $this->fail('Stock exceeds the supported quantity range.');
        $item->stock_balance = $this->decimal($after);
        $item->stock_revision++;
        $item->needs_review = count($item->reviewFlags()) > 0;
        $item->save();
        DB::table('miri_construction_stock_movements')->insert([
            'branch_id' => $item->branch_id, 'construction_item_id' => $item->id, 'kind' => $kind,
            'unit' => $item->unit, 'quantity' => $this->decimal($delta), 'balance_before' => $before,
            'balance_after' => $item->stock_balance, 'location' => $item->current_location,
            'reference' => $reference, 'note' => $note, 'request_key' => $key, 'request_hash' => $hash,
            'cog_item_id' => $line, 'related_item_id' => $related, 'user_id' => $user, 'created_at' => now(),
        ]);
    }
    public function manual(int $id, int $branch, array $data, int $user): void
    {
        DB::transaction(function () use ($id, $branch, $data, $user) {
            Branch::whereKey($branch)->lockForUpdate()->firstOrFail();
            $item = MiriConstructionItem::where('branch_id', $branch)->whereKey($id)->lockForUpdate()->firstOrFail();
            $key = 'manual:'.$data['request_key'];
            $hash = hash('sha256', json_encode([$id, $branch, $data['kind'], $data['quantity'], $data['unit'] ?? null, $data['location'] ?? null, $data['reference'] ?? null, $data['note']]));
            $existing = DB::table('miri_construction_stock_movements')->where('request_key', $key)->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) $this->fail('This submission has already been used. Refresh before posting another movement.');
                return;
            }
            if (! hash_equals($this->token($item), $data['stock_token'])) $this->fail('Stock changed since this page was opened. Refresh and review the current balance.');
            $quantity = $this->milli($data['quantity']);
            if ($data['kind'] === 'opening') {
                if ($item->stock_initialized_at) $this->fail('The opening balance has already been verified. Use a reviewed correction instead.');
                if (blank($data['unit'] ?? null) || blank($data['location'] ?? null)) $this->fail('Opening stock requires a unit and a storage location.');
                $item->unit = $this->unit($data['unit']);
                $item->current_location = trim($data['location']);
                $item->stock_initialized_at = now();
            } else {
                $this->ready($item);
                if ($data['kind'] === 'writeoff') $quantity = -$quantity;
                if ($data['kind'] === 'correction') $quantity -= $this->milli($item->stock_balance);
            }
            $this->post($item, $data['kind'], $quantity, $user, $key, $data['note'], $data['reference'] ?? null, hash: $hash);
        });
    }
    public function confirm(MiriCog $cog, int $user): void
    {
        DB::transaction(function () use ($cog, $user) {
            Branch::whereKey($cog->branch_id)->lockForUpdate()->firstOrFail();
            $cog = MiriCog::whereKey($cog->id)->lockForUpdate()->firstOrFail();
            if (! $cog->construction_stock_workflow) $this->fail('Historical COGs cannot be replayed into stock. Create a new movement for new activity.');
            if ($cog->construction_stock_confirmed_at) return;
            if (! in_array($cog->status, ['draft', 'signed'])) $this->fail('This COG cannot be confirmed.');
            $lines = $cog->items()->where('item_type', 'Construction')->orderBy('id')->get();
            if ($lines->isEmpty()) $this->fail('No TEC, Garnet & PPE items on this note.');
            foreach ($lines as $line) {
                $item = MiriConstructionItem::where('branch_id', $cog->branch_id)->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
                $this->ready($item);
                if ($this->unit($line->unit) !== $this->unit($item->unit)) $this->fail("Record #{$item->id}: COG unit must match {$item->unit}. No unit conversion is applied.");
                if (trim((string) $line->current_location) !== trim((string) $item->current_location)) $this->fail('The source location changed. Create a new COG with the correct location.');
                $quantity = $this->milli($line->quantity);
                $kind = match ($cog->movement_type) { 'Issue out' => 'issue', 'Transfer' => 'transfer_out', 'Return to supplier' => 'supplier_return', 'Received backload' => 'backload', default => $this->fail('Unsupported stock movement.') };
                $destination = null;
                if ($kind === 'transfer_out') {
                    $destination = MiriConstructionItem::where('branch_id', $cog->branch_id)->whereKey($line->construction_destination_id)->lockForUpdate()->first();
                    if (! $destination || $destination->id === $item->id) $this->fail('Select a different destination stock record for each transfer.');
                    $this->ready($destination);
                    foreach (['company', 'category', 'section_1', 'section_2', 'description', 'model_brand', 'serial_no', 'tag_no', 'unit'] as $field) {
                        if ($this->unit($destination->$field) !== $this->unit($item->$field)) $this->fail("Transfer destination must match the source item, company and unit ({$field}).");
                    }
                    if ($this->unit($destination->current_location) === $this->unit($item->current_location)) $this->fail('Transfer destination must be at a different location.');
                    if ($this->unit($cog->to_location) !== $this->unit($destination->current_location)) $this->fail('The COG To location must match the destination stock record.');
                }
                if ($kind === 'backload') {
                    $outstanding = -$this->milli(DB::table('miri_construction_stock_movements')->where('construction_item_id', $item->id)->whereIn('kind', ['issue', 'backload'])->sum('quantity'));
                    if ($quantity > $outstanding) $this->fail('Backload exceeds confirmed outstanding issues. Record legacy returns as a reviewed correction.');
                }
                $this->post($item, $kind, $kind === 'backload' ? $quantity : -$quantity, $user, 'cog:'.$line->id.':source', $cog->movement_type, $cog->cog_no, $line->id, $destination?->id);
                if ($destination) $this->post($destination, 'transfer_in', $quantity, $user, 'cog:'.$line->id.':destination', 'Transfer received', $cog->cog_no, $line->id, $item->id);
            }
            $cog->update(['construction_stock_confirmed_at' => now(), 'status' => $cog->status === 'draft' ? 'confirmed' : $cog->status, 'updated_by' => $user]);
        });
    }
}
