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
            $exists = $item->exists;
            if ($exists) $item = MiriPaintItem::whereKey($item->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            $before = $item->toArray();
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
            app(AuditLogger::class)->record('miri_paint', $exists ? 'updated' : 'created',
                ($exists ? 'Updated' : 'Registered').' Paint record #'.$item->id.'. Stock snapshots saved; no automatic movements.',
                $item, before: $exists ? $before : null, after: $item->toArray(), user: $user, request: $request);
            return $item;
        });
    }
}
