<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRegisterSheetRequest;
use App\Models\{Branch, MiriRentalItem, MiriConstructionItem, MiriPaintItem};
use App\Services\{AuditLogger, BranchContext, ConstructionRecordService, PaintRecordService};
use App\Support\EquipmentSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterSheetController extends Controller
{
    public function update(SaveRegisterSheetRequest $request, string $register)
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        $model = match ($register) {
            'rental' => MiriRentalItem::class,
            'construction' => MiriConstructionItem::class,
            'paint' => MiriPaintItem::class,
        };
        $data = $request->validated();
        DB::transaction(function () use ($request, $register, $model, $branch, $data) {
            // Paint stock services lock the branch before individual records.
            if ($register === 'paint') Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $items = $model::where('branch_id', $branch->id)->whereIn('id', array_column($data['rows'], 'id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($items->count() === count($data['rows']), 404);
            foreach ($data['rows'] as $index => $row) {
                $item = $items[$row['id']];
                try {
                    foreach ($row['original'] as $key => $original) {
                        if (EquipmentSheet::value($item, $key) !== (string) ($original ?? '')) {
                            throw ValidationException::withMessages([$key => 'This cell changed since you opened it. Discard changes and reload before editing it again.']);
                        }
                    }
                    if ($register === 'construction') {
                        app(ConstructionRecordService::class)->save($item, $row['changes'], $branch->id, $request->user(), $request);
                    } elseif ($register === 'paint') {
                        $changes = [...$row['changes'], 'date_status' => $item->date_status,
                            'manufacture_date' => $item->manufacture_date?->format('Y-m-d'),
                            'best_before_date' => $item->best_before_date?->format('Y-m-d')];
                        app(PaintRecordService::class)->save($item, $changes, $branch->id, $request->user(), $request);
                    } else {
                        $before = $item->only(array_keys($row['changes']));
                        $item->fill($row['changes'])->save();
                        app(AuditLogger::class)->record('miri_rentals', 'updated', "Updated rental #{$item->id} in Excel view.", $item,
                            before: $before, after: $item->only(array_keys($row['changes'])), user: $request->user(), request: $request);
                    }
                } catch (ValidationException $error) {
                    $errors = [];
                    foreach ($error->errors() as $key => $messages) $errors["rows.{$index}.changes.{$key}"] = $messages;
                    throw ValidationException::withMessages($errors);
                }
            }
        });
        return response()->json(['message' => count($data['rows']).' records saved.']);
    }
}
