<?php

namespace App\Http\Controllers;

use App\Actions\Assets\RecordAssetMovementAction;
use App\Enums\AssetCondition;
use App\Enums\AssetMovementType;
use App\Http\Requests\StoreAssetMovementRequest;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssetMovementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Assets/Movements/Index', [
            'movements' => AssetMovement::query()
                ->with(['asset', 'fromLocation', 'toLocation', 'creator'])
                ->latest('movement_date')
                ->latest('id')
                ->paginate(20)
                ->through(fn (AssetMovement $movement) => [
                    'id' => $movement->id,
                    'movement_date' => $movement->movement_date->format('Y-m-d'),
                    'asset_tag_no' => $movement->asset->asset_tag_no,
                    'description' => $movement->asset->description,
                    'movement_type' => $movement->movement_type->value,
                    'from_location' => $movement->fromLocation?->name,
                    'to_location' => $movement->toLocation?->name,
                    'to_status' => $movement->to_status->value,
                    'handled_by' => $movement->handled_by,
                    'created_by' => $movement->creator?->name,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Assets/Movements/Create', [
            'assets' => Asset::query()
                ->with(['currentLocation', 'category'])
                ->orderBy('asset_tag_no')
                ->get()
                ->map(fn (Asset $asset) => [
                    'value' => $asset->id,
                    'label' => "{$asset->asset_tag_no} - {$asset->description}",
                    'status' => $asset->current_status->value,
                    'condition' => $asset->current_condition?->value,
                    'location_id' => $asset->current_location_id,
                ]),
            'locations' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => "{$location->code} - {$location->name}"]),
            'movementTypes' => AssetMovementType::options(),
            'conditionOptions' => AssetCondition::options(),
        ]);
    }

    public function store(StoreAssetMovementRequest $request, RecordAssetMovementAction $action): RedirectResponse
    {
        $asset = Asset::findOrFail($request->validated('asset_id'));

        $action->execute($asset, $request->validated(), $request->user()->id);

        return redirect()->route('asset-movements.index')->with('success', 'Stock item movement recorded.');
    }
}
