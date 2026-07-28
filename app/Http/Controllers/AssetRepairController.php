<?php

namespace App\Http\Controllers;

use App\Actions\Assets\RecordAssetMovementAction;
use App\Enums\AssetCondition;
use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetRepairController extends Controller
{
    public function store(Request $request, RecordAssetMovementAction $action): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $asset = Asset::query()->findOrFail($data['asset_id']);

        if ($asset->assignments()->whereNull('returned_at')->exists()) {
            throw ValidationException::withMessages([
                'asset_id' => 'Check in this asset before sending it for repair.',
            ]);
        }

        $action->execute($asset, [
            ...$data,
            'movement_type' => AssetMovementType::SendForRepair->value,
            'from_location_id' => $asset->current_location_id,
        ], $request->user()->id);

        return back()->with('success', "{$asset->asset_tag_no} is now under repair.");
    }

    public function returnFromRepair(Request $request, Asset $asset, RecordAssetMovementAction $action): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        if ($asset->current_status !== AssetStatus::UnderRepair) {
            throw ValidationException::withMessages([
                'asset' => 'Only an asset currently under repair can be returned to service.',
            ]);
        }

        $data = $request->validate([
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'condition_after' => ['nullable', Rule::enum(AssetCondition::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->execute($asset, [
            ...$data,
            'movement_type' => AssetMovementType::ReturnFromRepair->value,
            'from_location_id' => $asset->current_location_id,
        ], $request->user()->id);

        return back()->with('success', "{$asset->asset_tag_no} returned from repair and is available.");
    }
}
