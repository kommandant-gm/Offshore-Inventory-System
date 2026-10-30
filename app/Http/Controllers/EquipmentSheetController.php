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
            $scaffolding = $request->routeIs('scaffolding.spreadsheet.update');
            $options = $scaffolding ? ScaffoldingStockController::options() : [];
            $items = MajorEquipment::where('branch_id', $branch->id)->scaffolding($scaffolding)
                ->when(! $scaffolding, fn ($q) => $q->where('inventory_type', $data['inventory_type']))
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
                if ($scaffolding) {
                    $groups = $options['descriptionsBySubcategory'];
                    $subcategory = $changes['section_2'] ?? $item->section_2;
                    $description = $changes['description'] ?? $item->description;
                    if (array_key_exists('section_2', $changes) && isset($groups[$subcategory])
                        && in_array($description, array_merge(...array_values($groups)), true)
                        && ! in_array($description, $groups[$subcategory], true)) {
                        throw ValidationException::withMessages(["rows.{$index}.changes.section_2" => 'Select a description matching the new subcategory.']);
                    }
                    foreach (['section_2' => 'subcategory', 'description' => 'description', 'scaffolding_contract' => 'contract', 'current_location' => 'yard'] as $key => $kind) {
                        if (! array_key_exists($key, $changes) || blank($changes[$key]) || $changes[$key] === $item->$key) continue;
                        $allowed = $kind === 'yard' ? ['BINTULU', 'LABUAN'] : collect($options[$kind])->all();
                        if ($key === 'description') {
                            $groups = $options['descriptionsBySubcategory'];
                            $subcategory = $changes['section_2'] ?? $item->section_2;
                            if (isset($groups[$subcategory])) $allowed = array_merge($groups[$subcategory], array_diff($allowed, array_merge(...array_values($groups))));
                        }
                        if (! in_array($changes[$key], $allowed, true)) throw ValidationException::withMessages(["rows.{$index}.changes.{$key}" => 'Choose a dropdown option. Add new scaffold options from the register details first.']);
                    }
                }
                if (array_key_exists('section_1', $changes)) {
                    $section = trim((string) $changes['section_1']);
                    $changes['section_1'] = $item->inventory_type === 'cargo' ? 'CARGO SET'
                        : (in_array(strtoupper($section), ['MACHINARY', 'MACHINERY'], true) ? 'Machinery' : $section);
                }
                $before = $item->only(array_keys($changes));
                try { $item->fill($changes)->save(); }
                catch (ValidationException $error) {
                    throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(fn ($messages, $key) => ["rows.{$index}.changes.{$key}" => $messages])->all());
                }
                $audit->record('miri_inventory', 'updated', "Updated equipment #{$item->id} in Excel view.", $item,
                    before: $before, after: $item->only(array_keys($changes)), user: $request->user(), request: $request);
            }
        });
        return response()->json(['message' => count($data['rows']).' records saved.']);
    }
}
