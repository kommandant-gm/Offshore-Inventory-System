<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:100'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:100'],
            'assignment' => ['nullable', 'in:assigned,unassigned'],
        ]);
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

        $selectedCategory = $request->filled('category')
            ? $categorySummaries->firstWhere('id', (int) $request->integer('category'))
            : null;

        $assetQuery = Asset::query()
            ->with(['category', 'currentLocation', 'currentAssignment'])
            ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('asset_tag_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_no', 'like', "%{$search}%")
                        ->orWhereHas('currentAssignment', fn ($assignment) => $assignment
                            ->where('assigned_to_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('current_location_id', $location))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('current_status', $status))
            ->when($filters['department'] ?? null, fn ($query, $department) => $query->whereHas(
                'currentAssignment', fn ($assignment) => $assignment->where('department', $department)
            ))
            ->when($filters['assignee'] ?? null, fn ($query, $assignee) => $query->whereHas(
                'currentAssignment', fn ($assignment) => $assignment->where('assigned_to_name', $assignee)
            ))
            ->when($filters['os'] ?? null, fn ($query, $os) => $query->where('operating_system', $os))
            ->when(($filters['assignment'] ?? null) === 'assigned', fn ($query) => $query->whereHas('currentAssignment'))
            ->when(($filters['assignment'] ?? null) === 'unassigned', fn ($query) => $query->whereDoesntHave('currentAssignment'))
            ->orderBy('asset_tag_no');

        $qrCodesMissing = (clone $assetQuery)->whereNull('public_token')->count();
        $assets = $assetQuery
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
                'serial_no' => $asset->serial_no,
                'model' => $asset->model,
                'operating_system' => $asset->operating_system,
                'purchase_year' => $asset->purchase_year,
                'assigned_to' => $asset->currentAssignment?->assigned_to_name,
                'department' => $asset->currentAssignment?->department,
                'is_assigned' => $asset->currentAssignment !== null,
                'has_qr_code' => filled($asset->public_token),
            ]);

        $assignedAssetsByDepartment = Asset::query()
            ->whereHas('currentAssignment')
            ->with('currentAssignment')
            ->get(['id', 'current_status'])
            ->groupBy(fn (Asset $asset) => trim((string) $asset->currentAssignment?->department) ?: 'Unspecified')
            ->map(function ($departmentAssets, string $department) {
                $statuses = $departmentAssets
                    ->countBy(fn (Asset $asset) => $asset->current_status->value)
                    ->all();

                return [
                    'department' => $department,
                    'total' => $departmentAssets->count(),
                    'statuses' => $statuses,
                ];
            })
            ->sortByDesc('total')
            ->values();

        return Inertia::render('ItAssets/Index', [
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
            'qrCodesMissing' => $qrCodesMissing,
            'assignedAssetsByDepartment' => $assignedAssetsByDepartment,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'location' => isset($filters['location']) ? (string) $filters['location'] : '',
                'status' => $filters['status'] ?? '',
                'department' => $filters['department'] ?? '',
                'assignee' => $filters['assignee'] ?? '',
                'os' => $filters['os'] ?? '',
                'assignment' => $filters['assignment'] ?? '',
            ],
            'selectedCategoryId' => $selectedCategory?->id,
            'locationOptions' => Location::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => "{$location->code} - {$location->name}"]),
            'statusOptions' => AssetStatus::options(),
            'conditionOptions' => AssetCondition::options(),
            'departmentOptions' => Asset::query()
                ->whereHas('currentAssignment', fn ($query) => $query->whereNotNull('department')->where('department', '<>', ''))
                ->with('currentAssignment')
                ->get()
                ->pluck('currentAssignment.department')
                ->filter()->unique()->sort()->values(),
            'osOptions' => Asset::query()
                ->whereNotNull('operating_system')->where('operating_system', '<>', '')
                ->distinct()->orderBy('operating_system')->pluck('operating_system'),
            'userOptions' => $this->assignmentUsers($request),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        return Inertia::render('ItAssets/Create', [
            'categories' => Category::query()->whereIn('type', ['asset', 'both'])->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => AssetStatus::options(),
            'conditions' => AssetCondition::options(),
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $data = $request->validated();
        DB::transaction(function () use ($data, $request) {
            $asset = Asset::create(collect($data)->except(['assigned_to_name', 'department', 'assigned_at'])->all());
            if (! empty($data['assigned_to_name'])) {
                $asset->assignments()->create([
                    'assigned_to_name' => $data['assigned_to_name'],
                    'department' => $data['department'] ?? null,
                    'assigned_at' => $data['assigned_at'] ?? now()->toDateString(),
                    'assigned_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('it-assets.index')->with('success', 'IT asset registered.');
    }

    public function show(Asset $asset): Response
    {
        abort_unless(request()->user()?->canRead('it_assets'), 403);
        $asset->load(['category', 'currentLocation', 'currentAssignment', 'assignments', 'movements.fromLocation', 'movements.toLocation', 'movements.creator']);

        return Inertia::render('ItAssets/Show', [
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
                'operating_system' => $asset->operating_system,
                'purchase_year' => $asset->purchase_year,
                'age' => $asset->purchase_year ? now()->year - $asset->purchase_year : null,
                'assigned_to' => $asset->currentAssignment?->assigned_to_name,
                'department' => $asset->currentAssignment?->department,
                'is_assigned' => $asset->currentAssignment !== null,
                'has_qr_code' => filled($asset->public_token),
                'assignments' => $asset->assignments->map(fn ($assignment) => [
                    'assigned_to_name' => $assignment->assigned_to_name,
                    'department' => $assignment->department,
                    'assigned_at' => $assignment->assigned_at?->format('Y-m-d'),
                    'returned_at' => $assignment->returned_at?->format('Y-m-d'),
                ]),
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
            'userOptions' => $this->assignmentUsers(request()),
        ]);
    }

    public function edit(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        return Inertia::render('ItAssets/Edit', [
            'asset' => [
                'id' => $asset->id,
                'asset_tag_no' => $asset->asset_tag_no,
                'serial_no' => $asset->serial_no,
                'description' => $asset->description,
                'category_id' => $asset->category_id,
                'brand' => $asset->brand,
                'model' => $asset->model,
                'operating_system' => $asset->operating_system,
                'purchase_year' => $asset->purchase_year,
                'current_location_id' => $asset->current_location_id,
                'storage_position' => $asset->storage_position,
                'current_status' => $asset->current_status->value,
                'current_condition' => $asset->current_condition?->value,
                'acquisition_date' => $asset->acquisition_date?->format('Y-m-d'),
                'acquisition_cost' => $asset->acquisition_cost,
                'ownership' => $asset->ownership,
                'active' => $asset->active,
                'remarks' => $asset->remarks,
            ],
            'categories' => Category::query()->whereIn('type', ['asset', 'both'])->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => AssetStatus::options(),
            'conditions' => AssetCondition::options(),
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $asset->update($request->validated());

        return redirect()->route('it-assets.show', $asset)->with('success', 'IT asset updated.');
    }

    private function assignmentUsers(Request $request): array
    {
        $branchId = app(\App\Services\BranchContext::class)->id($request->user());

        return User::query()
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branch) => $branch->where('branches.id', $branchId)))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->username,
                'email' => $user->email,
            ])->all();
    }
}
