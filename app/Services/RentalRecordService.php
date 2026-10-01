<?php

namespace App\Services;

use App\Http\Requests\SaveMiriRentalRequest;
use App\Models\MiriRentalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RentalRecordService
{
    public function save(MiriRentalItem $item, SaveMiriRentalRequest $request): MiriRentalItem
    {
        $newFiles = [];
        $obsoleteFiles = [];
        try {
            $item = DB::transaction(function () use ($item, $request, &$newFiles, &$obsoleteFiles) {
                $branchId = app(BranchContext::class)->id($request->user());
                $exists = $item->exists;
                if ($exists) $item = MiriRentalItem::whereKey($item->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
                $before = $item->toArray();
                $item->fill(collect($request->validated())->except('uploads')->all());
                $item->branch_id = $branchId;
                $item->save();
                $attachments = $item->attachments ?? [];
                foreach (MiriRentalItem::ATTACHMENTS as $slot => $label) {
                    $file = $request->file("uploads.{$slot}");
                    if (! $file) continue;
                    $path = $file->store('attachments/'.$branchId.'/'.$item->id, 'rental');
                    $newFiles[] = $path;
                    if (isset($attachments[$slot])) $obsoleteFiles[] = $attachments[$slot]['path'];
                    $attachments[$slot] = [
                        'path' => $path,
                        'name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'mime' => 'application/pdf', 'size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id, 'uploaded_at' => now()->toIso8601String(),
                    ];
                }
                $item->attachments = $attachments;
                $item->save();
                app(AuditLogger::class)->record('miri_rentals', $exists ? 'updated' : 'created',
                    ($exists ? 'Updated' : 'Added')." Miri rental record {$item->description}.", $item,
                    before: $exists ? $before : null,
                    after: [...$item->toArray(), 'attachment_slots' => array_keys($attachments)],
                    user: $request->user(), request: $request);
                return $item;
            });
        } catch (\Throwable $error) {
            $this->cleanup($newFiles);
            throw $error;
        }
        $this->cleanup($obsoleteFiles);
        return $item;
    }

    private function cleanup(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try { Storage::disk('rental')->delete($path); }
            catch (\Throwable $error) { Log::warning('Rental attachment cleanup failed', ['path' => $path]); }
        }
    }
}
