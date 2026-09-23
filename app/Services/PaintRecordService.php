<?php
namespace App\Services;

use App\Models\MiriPaintItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaintRecordService
{
    public function save(MiriPaintItem $item, array $data, int $branchId, $user, $request): MiriPaintItem
    {
        return DB::transaction(function () use ($item, $data, $branchId, $user, $request) {
            \App\Models\Branch::whereKey($branchId)->lockForUpdate()->firstOrFail();
            $ledger = app(PaintStockLedger::class);
            $exists = $item->exists;
            if ($exists) $item = MiriPaintItem::whereKey($item->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            if ($exists) $ledger->rollItem($item);
            $before = $item->toArray();
            $stockChanged = false;
            foreach (['opening_cans', 'opening_litres', 'balance_cans', 'balance_litres'] as $field) {
                if (! array_key_exists($field, $data)) continue;
                $value = $data[$field] === null ? null : number_format((float) $data[$field], 3, '.', '');
                if ($item->$field !== $value) $stockChanged = true;
            }
            if ($exists && $stockChanged) {
                if (($data['stock_token'] ?? '') !== $ledger->token($item)) throw ValidationException::withMessages(['stock_token' => 'Stock changed or this form is outdated. Reload the record before adjusting stock.']);
                if (blank($data['review_note'] ?? null)) throw ValidationException::withMessages(['review_note' => 'Explain this stock adjustment.']);
            }
            unset($data['stock_token']);
            $dateChanged = $exists && ($item->date_status !== $data['date_status']
                || $item->manufacture_date?->format('Y-m-d') !== ($data['manufacture_date'] ?? null)
                || $item->best_before_date?->format('Y-m-d') !== ($data['best_before_date'] ?? null));
            if ($dateChanged && blank($data['review_note'] ?? null)) throw ValidationException::withMessages(['review_note' => 'Explain how the date meaning was verified or corrected.']);
            if (filled($item->original_date) && $data['date_status'] === 'not_recorded') throw ValidationException::withMessages(['date_status' => 'A source date exists. Keep it unconfirmed or confirm its meaning.']);
            // A routine edit must not erase the previous date-review explanation.
            if (blank($data['review_note'] ?? null)) unset($data['review_note']);
            $item->fill($data);
            $item->branch_id = $branchId;
            $warnings = $item->import_warnings ?? [];
            foreach (array_keys($warnings) as $field) {
                if (array_key_exists($field, $data) && filled($data[$field]) && filled($data['review_note'] ?? null)) unset($warnings[$field]);
            }
            $item->import_warnings = $warnings;
            $item->save();
            $ledger->adjustment($item, $before, $user->id, $data['review_note'] ?? ($exists ? null : 'Initial recorded stock'));
            app(AuditLogger::class)->record('miri_paint', $exists ? 'updated' : 'created',
                ($exists ? 'Updated' : 'Registered').' Paint record #'.$item->id.'. Stock changes recorded as adjustments; historical issue fields are not replayed.',
                $item, before: $exists ? $before : null, after: $item->toArray(), user: $user, request: $request);
            return $item;
        });
    }
}
