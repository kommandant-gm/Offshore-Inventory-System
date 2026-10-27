<?php

namespace App\Services;

use App\Models\{Branch, MajorEquipment, MiriCog};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScaffoldingStockLedger
{
    public const YARDS = ['BINTULU', 'LABUAN'];

    private function fail(string $message): never { throw ValidationException::withMessages(['stock' => $message]); }
    private function milli($value): int { return (int) round((float) $value * 1000); }
    private function decimal(int $value): string { return number_format($value / 1000, 3, '.', ''); }

    public function token(?object $account): string
    {
        return hash('sha256', json_encode($account ? [$account->id, $account->revision, $account->balance, $account->unit] : null));
    }

    private function item(int $id, int $branch): MajorEquipment
    {
        return MajorEquipment::where('branch_id', $branch)->scaffolding()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function account(MajorEquipment $item, string $yard): object
    {
        $account = DB::table('scaffolding_stock_accounts')->where('equipment_id', $item->id)->where('yard', $yard)->lockForUpdate()->first();
        if (! $account) $this->fail("Verify opening stock for {$yard} before confirming movements.");
        if (strtoupper(trim((string) $item->unit)) !== $account->unit) $this->fail('The register unit differs from the verified stock unit. Review it before continuing.');
        return $account;
    }

    private function post(object $account, string $kind, int $delta, array $data, string $key, string $hash, int $user, ?int $line = null): void
    {
        $balance = $this->milli($account->balance) + $delta;
        if ($balance < 0) $this->fail("Insufficient {$account->yard} stock. Available: {$account->balance} {$account->unit}.");
        if ($balance > 99999999999999) $this->fail('Balance exceeds the supported stock range.');
        DB::table('scaffolding_stock_accounts')->where('id', $account->id)->update([
            'balance' => $this->decimal($balance), 'revision' => $account->revision + 1, 'updated_at' => now(),
        ]);
        DB::table('scaffolding_stock_movements')->insert([
            'account_id' => $account->id, 'kind' => $kind, 'quantity' => $this->decimal($delta),
            'balance_after' => $this->decimal($balance), 'destination' => $data['destination'] ?? null,
            'contract' => $data['contract'] ?? null, 'mr_reference' => $data['mr_reference'] ?? null,
            'reference' => $data['reference'] ?? null, 'document_date' => $data['document_date'],
            'note' => $data['note'], 'cog_item_id' => $line, 'request_key' => $key, 'request_hash' => $hash,
            'user_id' => $user, 'created_at' => now(),
        ]);
    }

    private function backload(object $account, int $quantity): void
    {
        $outstanding = -$this->milli(DB::table('scaffolding_stock_movements')->where('account_id', $account->id)
            ->whereIn('kind', ['issue', 'backload'])->sum('quantity'));
        if ($quantity > $outstanding) $this->fail('Return quantity exceeds confirmed outstanding issues. Use a reviewed correction for legacy returns.');
    }

    // The same equipment/reference cannot be entered manually and via an Internal Issue Note.
    private function reference(MajorEquipment $item, ?string $reference, bool $manual): void
    {
        if (blank($reference)) return;
        $reference = strtoupper(trim($reference));
        if ($manual && MiriCog::where('branch_id', $item->branch_id)->whereRaw('UPPER(TRIM(cog_no)) = ?', [$reference])->exists()) {
            $this->fail('This COG is an Internal Issue Note. Confirm stock from that note instead of entering it again here.');
        }
        $query = DB::table('scaffolding_stock_movements as m')->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')
            ->where('a.equipment_id', $item->id)->whereRaw('UPPER(TRIM(m.reference)) = ?', [$reference]);
        if (! $manual) $query->whereNull('m.cog_item_id');
        if ($query->exists()) $this->fail('This document reference already has a stock movement for this item. Review its history to avoid a duplicate entry.');
    }

    public function manual(int $id, int $branch, array $data, int $user): void
    {
        DB::transaction(function () use ($id, $branch, $data, $user) {
            Branch::whereKey($branch)->lockForUpdate()->firstOrFail();
            $item = $this->item($id, $branch);
            $key = 'manual:'.$data['request_key'];
            $hash = hash('sha256', json_encode([$id, $branch, collect($data)->except(['stock_token', 'confirmed'])->all()]));
            $existing = DB::table('scaffolding_stock_movements')->where('request_key', $key.':0')->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) $this->fail('This submission key was already used. Reload before posting new stock.');
                return;
            }
            $account = DB::table('scaffolding_stock_accounts')->where('equipment_id', $id)->where('yard', $data['yard'])->lockForUpdate()->first();
            if (! hash_equals($this->token($account), $data['stock_token'])) $this->fail('Stock changed since you opened this page. Reload and review the balance.');
            if ($data['kind'] === 'opening') {
                if ($account) $this->fail('Opening stock is already verified. Use a reviewed correction.');
                $unit = strtoupper(trim((string) $item->unit));
                if ($unit === '') $this->fail('Save a unit in the scaffolding details before verifying stock.');
                if (mb_strlen($unit) > 20) $this->fail('Use a stock unit of at most 20 characters.');
                $accountId = DB::table('scaffolding_stock_accounts')->insertGetId([
                    'branch_id' => $branch, 'equipment_id' => $id, 'yard' => $data['yard'], 'unit' => $unit,
                    'opening_quantity' => $data['quantity'], 'balance' => 0, 'revision' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $account = DB::table('scaffolding_stock_accounts')->find($accountId);
            } else $account = $this->account($item, $data['yard']);

            $rows = $data['kind'] === 'issue' ? $data['rows'] : [$data];
            foreach ($rows as $i => $row) {
                $account = $this->account($item, $data['yard']);
                $quantity = $this->milli($row['quantity']);
                $kind = $data['kind'];
                if ($kind !== 'opening') $this->reference($item, $row['reference'] ?? null, true);
                if ($kind === 'backload') $this->backload($account, $quantity);
                $delta = match ($kind) { 'issue', 'writeoff' => -$quantity, 'correction' => $quantity - $this->milli($account->balance), default => $quantity };
                $this->post($account, $kind, $delta, $row, $key.':'.$i, $hash, $user);
            }
        });
    }

    public function confirm(MiriCog $document, int $user): void
    {
        DB::transaction(function () use ($document, $user) {
            Branch::whereKey($document->branch_id)->lockForUpdate()->firstOrFail();
            $cog = MiriCog::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (! $cog->scaffolding_stock_workflow) $this->fail('Historical notes cannot be replayed into scaffolding stock.');
            if ($cog->scaffolding_stock_confirmed_at) return;
            if (! in_array($cog->status, ['draft', 'signed', 'confirmed'])) $this->fail('This note cannot be confirmed.');
            $lines = $cog->items()->where('scaffolding_stock', true)->orderBy('id')->get();
            if ($lines->isEmpty()) $this->fail('No scaffolding stock items on this note.');
            foreach ($lines as $line) {
                $item = $this->item($line->item_id, $cog->branch_id);
                $account = $this->account($item, $line->scaffolding_yard);
                if (strtoupper(trim($line->unit)) !== $account->unit) $this->fail('COG unit must match the verified scaffolding unit.');
                $this->reference($item, $cog->cog_no, false);
                $quantity = $this->milli($line->quantity);
                $kind = match ($cog->movement_type) { 'Issue out' => 'issue', 'Received backload' => 'backload', 'Return to supplier' => 'supplier_return', 'Transfer' => 'transfer_out', default => $this->fail('Unsupported stock movement.') };
                $data = ['reference' => $cog->cog_no, 'document_date' => $cog->document_date->format('Y-m-d'),
                    'destination' => $cog->destination ?: $cog->to_location, 'contract' => $line->scaffolding_contract,
                    'mr_reference' => mb_substr((string) $line->mr_reference, 0, 255), 'note' => $line->remarks ?: $cog->movement_type];
                if ($kind === 'backload') $this->backload($account, $quantity);
                $destination = null;
                if ($kind === 'transfer_out') {
                    $yard = strtoupper(trim((string) ($cog->destination ?: $cog->to_location)));
                    if (! in_array($yard, self::YARDS, true) || $yard === $line->scaffolding_yard) $this->fail('For a scaffolding yard transfer, set Destination to the other yard: BINTULU or LABUAN.');
                    $destination = $this->account($item, $yard);
                }
                $hash = hash('sha256', 'cog:'.$line->id);
                $this->post($account, $kind, $kind === 'backload' ? $quantity : -$quantity, $data, 'cog:'.$line->id.':source', $hash, $user, $line->id);
                if ($destination) $this->post($destination, 'transfer_in', $quantity, $data, 'cog:'.$line->id.':destination', $hash, $user, $line->id);
            }
            $cog->update(['scaffolding_stock_confirmed_at' => now(), 'status' => $cog->status === 'draft' ? 'confirmed' : $cog->status, 'updated_by' => $user]);
        });
    }
}
