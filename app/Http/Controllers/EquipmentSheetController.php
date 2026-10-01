<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEquipmentSheetRequest;
use App\Models\MajorEquipment;
use App\Services\{AuditLogger, BranchContext};
use App\Support\EquipmentSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentSheetController extends Controller
{
    public function update(SaveEquipmentSheetRequest $request, AuditLogger $audit)
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        $data = $request->validated();
        DB::transaction(function () use ($data, $branch, $request, $audit) {
            $items = MajorEquipment::where('branch_id', $branch->id)->where('inventory_type', $data['inventory_type'])
                ->whereIn('id', array_column($data['rows'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($items->count() === count($data['rows']), 404);
            foreach ($data['rows'] as $index => $row) {
                $item = $items[$row['id']];
                foreach ($row['original'] as $key => $original) {
                    if (EquipmentSheet::value($item, $key) !== (string) ($original ?? '')) {
                        throw ValidationException::withMessages(["rows.{$index}.changes.{$key}" => 'This cell changed since you opened it. Discard changes and reload before editing it again.']);
                    }
                }
                $changes = $row['changes'];
                if (array_key_exists('section_1', $changes)) {
                    $section = trim((string) $changes['section_1']);
                    $changes['section_1'] = $item->inventory_type === 'cargo' ? 'CARGO SET'
                        : (in_array(strtoupper($section), ['MACHINARY', 'MACHINERY'], true) ? 'Machinery' : $section);
                }
                $before = $item->only(array_keys($changes));
                $item->fill($changes)->save();
                $audit->record('miri_inventory', 'updated', "Updated equipment #{$item->id} in Excel view.", $item,
                    before: $before, after: $item->only(array_keys($changes)), user: $request->user(), request: $request);
            }
        });
        return response()->json(['message' => count($data['rows']).' records saved.']);
    }
}
