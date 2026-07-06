<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $categorySummaries = Category::query()
            ->whereIn('type', ['asset', 'both'])
            ->withCount([
                'assets as total_assets',
                'assets as available_assets' => fn ($query) => $query->where('current_status', AssetStatus::Available->value),
                'assets as deployed_assets' => fn ($query) => $query->where('current_status', AssetStatus::Deployed->value),
                'assets as repair_assets' => fn ($query) => $query->where('current_status', AssetStatus::UnderRepair->value),
                'assets as damaged_assets' => fn ($query) => $query->where('current_status', AssetStatus::Damaged->value),
                'assets as disposed_assets' => fn ($query) => $query->where('current_status', AssetStatus::Disposed->value),
            ])
            ->orderBy('name')
            ->get();

        $selectedCategory = $categorySummaries->firstWhere('id', (int) $request->integer('category'))
            ?? $categorySummaries->first();

        $assets = Asset::query()
            ->with(['category', 'currentLocation'])
            ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
            ->orderBy('asset_tag_no')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_tag_no' => $asset->asset_tag_no,
                'description' => $asset->description,
                'category' => $asset->category->name,
                'location' => $asset->currentLocation?->name,
                'status' => $asset->current_status->value,
                'condition' => $asset->current_condition?->value,
                'active' => $asset->active,
            ]);

        return Inertia::render('Assets/Index', [
            'categories' => $categorySummaries->map(fn (Category $category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'type' => $category->type->value,
                'total_assets' => $category->total_assets,
                'available_assets' => $category->available_assets,
                'deployed_assets' => $category->deployed_assets,
                'repair_assets' => $category->repair_assets,
                'damaged_assets' => $category->damaged_assets,
                'disposed_assets' => $category->disposed_assets,
                'active' => $category->active,
            ]),
            'assets' => $assets,
            'selectedCategoryId' => $selectedCategory?->id,
            'locationOptions' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => "{$location->code} - {$location->name}"]),
            'statusOptions' => AssetStatus::options(),
            'conditionOptions' => AssetCondition::options(),
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        Asset::create($request->validated());

        return redirect()->route('assets.index')->with('success', 'Stock item created.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load(['category', 'currentLocation', 'movements.fromLocation', 'movements.toLocation', 'movements.creator']);

        return Inertia::render('Assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'asset_tag_no' => $asset->asset_tag_no,
                'description' => $asset->description,
                'brand' => $asset->brand,
                'model' => $asset->model,
                'serial_no' => $asset->serial_no,
                'year' => $asset->year,
                'ownership' => $asset->ownership,
                'status' => $asset->current_status->value,
                'condition' => $asset->current_condition?->value,
                'location' => $asset->currentLocation?->name,
                'category' => $asset->category->name,
                'remarks' => $asset->remarks,
                'movements' => $asset->movements->map(fn ($movement) => [
                    'id' => $movement->id,
                    'movement_date' => $movement->movement_date->format('Y-m-d'),
                    'movement_type' => $movement->movement_type->value,
                    'from_location' => $movement->fromLocation?->name,
                    'to_location' => $movement->toLocation?->name,
                    'to_status' => $movement->to_status->value,
                    'condition_after' => $movement->condition_after?->value,
                    'reference_no' => $movement->reference_no,
                    'handled_by' => $movement->handled_by,
                    'remarks' => $movement->remarks,
                    'created_by' => $movement->creator?->name,
                ]),
            ],
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()->route('assets.show', $asset)->with('success', 'Stock item updated.');
    }
}
