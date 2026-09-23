<?php

namespace App\Services;

use App\Models\MiriConstructionItem;
use App\Support\ConstructionFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConstructionRecordService
{
    public function save(MiriConstructionItem $item, array $data, int $branchId, $user, $request): MiriConstructionItem
    {
        $newFiles = [];
        $obsoleteFiles = [];
        try {
            $item = DB::transaction(function () use ($item, $data, $branchId, $user, $request, &$newFiles, &$obsoleteFiles) {
                $exists = $item->exists;
                if ($exists) $item = MiriConstructionItem::whereKey($item->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
                if ($item->stock_initialized_at) {
                    foreach (['stock_balance', 'unit', 'current_location', 'company'] as $field) {
                        if (! array_key_exists($field, $data)) continue;
                        $same = $field === 'stock_balance'
                            ? ($data[$field] !== null && (float) $data[$field] === (float) $item->$field)
                            : trim((string) $data[$field]) === trim((string) $item->$field);
                        if (! $same) throw ValidationException::withMessages([$field => 'Stock tracking is active. Use a confirmed stock movement or correction; the unit, company and stock location are fixed.']);
                        unset($data[$field]);
                    }
                }
                $before = $item->toArray();
                $personnelBefore = $item->personnel_details;
                $item->fill(collect($data)->only([...array_column(ConstructionFields::FIELDS, 'key'), 'company', 'grouping_reviewed', 'review_note'])->all());
                $item->branch_id = $branchId;
                $warnings = $item->import_warnings ?? [];
                foreach (array_keys($warnings) as $field) if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') unset($warnings[$field]);
                $item->import_warnings = $warnings;
                $item->needs_review = count($item->reviewFlags()) > 0;
                $item->save();
                $attachments = $item->attachments ?? [];
                foreach (ConstructionFields::ATTACHMENTS as $slot => $label) {
                    $file = $data['uploads'][$slot] ?? null;
                    $remove = in_array($slot, $data['remove_attachments'] ?? [], true);
                    if ($file && $remove) throw ValidationException::withMessages(["uploads.{$slot}" => 'Choose replacement or removal, not both.']);
                    if (! $file && ! $remove) continue;
                    if (isset($attachments[$slot])) $obsoleteFiles[] = $attachments[$slot]['path'];
                    unset($attachments[$slot]);
                    if ($file) {
                        $path = $file->store('attachments/'.$branchId.'/'.$item->id, 'construction');
                        $newFiles[] = $path;
                        $attachments[$slot] = ['path' => $path, 'name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $user->id, 'uploaded_at' => now()->toIso8601String()];
                    }
                }
                $item->attachments = $attachments;
                $item->save();
                $after = $item->toArray();
                $after['personnel_details_changed'] = $personnelBefore !== $item->personnel_details;
                $after['attachment_slots'] = array_keys($attachments);
                app(AuditLogger::class)->record('miri_construction', $exists ? 'updated' : 'created',
                    ($exists ? 'Updated' : 'Registered').' Construction record #'.$item->id.'. Recorded values saved; no automatic movements.',
                    $item, before: $exists ? $before : null, after: $after, user: $user, request: $request);
                return $item;
            });
        } catch (\Throwable $error) { $this->cleanup($newFiles); throw $error; }
        $this->cleanup($obsoleteFiles);
        return $item;
    }

    private function cleanup(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try { Storage::disk('construction')->delete($path); }
            catch (\Throwable $error) { Log::warning('Construction attachment cleanup failed', ['path' => $path]); }
        }
    }
}
