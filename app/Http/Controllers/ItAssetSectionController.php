<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItAssetSectionController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $this->authorizeRead($request);

        $assets = Asset::query()
            ->with(['category:id,name', 'currentLocation:id,name'])
            ->get();

        $status = $assets
            ->countBy(fn (Asset $asset) => $asset->current_status->value)
            ->map(fn (int $total, string $value) => [
                'label' => str($value)->replace('_', ' ')->title()->toString(),
                'value' => $total,
            ])->values();

        $breakdown = fn (callable $label, int $limit = 8) => $assets
            ->countBy(fn (Asset $asset) => $label($asset) ?: 'Not specified')
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $total, string $name) => ['label' => $name, 'value' => $total])
            ->values();

        $currentYear = now()->year;
        $ageBands = [
            'Under 2 years' => 0,
            '2–4 years' => 0,
            '5–7 years' => 0,
            '8+ years' => 0,
            'Unknown' => 0,
        ];
        foreach ($assets as $asset) {
            $year = $asset->purchase_year ?: (is_numeric($asset->year) ? (int) $asset->year : null);
            $age = $year ? max(0, $currentYear - $year) : null;
            $band = $age === null ? 'Unknown'
                : ($age < 2 ? 'Under 2 years' : ($age < 5 ? '2–4 years' : ($age < 8 ? '5–7 years' : '8+ years')));
            $ageBands[$band]++;
        }

        return Inertia::render('ItAssets/Section', [
            'title' => 'IT Dashboard', 'description' => 'KL IT asset overview and lifecycle status.',
            'stats' => [
                ['label' => 'Total assets', 'value' => Asset::count()],
                ['label' => 'Assigned', 'value' => Asset::where('current_status', AssetStatus::Deployed->value)->count()],
                ['label' => 'Available', 'value' => Asset::where('current_status', AssetStatus::Available->value)->count()],
                ['label' => 'Under repair', 'value' => Asset::where('current_status', AssetStatus::UnderRepair->value)->count()],
            ],
            'charts' => [
                'status' => $status,
                'categories' => $breakdown(fn (Asset $asset) => $asset->category?->name),
                'locations' => $breakdown(fn (Asset $asset) => $asset->currentLocation?->name),
                'conditions' => $breakdown(fn (Asset $asset) => $asset->current_condition?->value
                    ? str($asset->current_condition->value)->replace('_', ' ')->title()->toString()
                    : null),
                'age' => collect($ageBands)->map(fn (int $total, string $label) => [
                    'label' => $label, 'value' => $total,
                ])->values(),
                'purchaseYears' => $assets
                    ->filter(fn (Asset $asset) => $asset->purchase_year)
                    ->countBy('purchase_year')
                    ->sortKeys()
                    ->map(fn (int $total, int|string $year) => ['label' => (string) $year, 'value' => $total])
                    ->values(),
            ],
        ]);
    }

    public function assignments(Request $request): Response
    {
        $this->authorizeRead($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'os' => ['nullable', 'string', 'max:100'],
        ]);
        $assignedAssets = Asset::query()->whereHas('currentAssignment');
        $rows = (clone $assignedAssets)
            ->with(['currentAssignment', 'category:id,name', 'currentLocation:id,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('asset_tag_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_no', 'like', "%{$search}%")
                        ->orWhereHas('currentAssignment', fn ($assignment) => $assignment
                            ->where('assigned_to_name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%"));
                });
            })
            ->when($filters['department'] ?? null, fn ($query, $department) => $query->whereHas('currentAssignment', fn ($assignment) => $assignment->where('department', $department)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('current_location_id', $location))
            ->when($filters['os'] ?? null, fn ($query, $os) => $query->where('operating_system', $os))
            ->orderBy('asset_tag_no')->paginate(20)->withQueryString()
            ->through(fn (Asset $asset) => [
                'asset_tag' => $asset->asset_tag_no, 'asset_id' => $asset->id,
                'detail' => $asset->currentAssignment?->assigned_to_name, 'meta' => $asset->currentAssignment?->department,
                'category' => $asset->category?->name, 'location' => $asset->currentLocation?->name, 'os' => $asset->operating_system,
            ]);

        return Inertia::render('ItAssets/Section', [
            'title' => 'Assignments / Returns', 'description' => 'Current device custody and assignment records.',
            'rows' => $rows,
            'filters' => [
                'search' => $filters['search'] ?? '', 'department' => $filters['department'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'location' => isset($filters['location']) ? (string) $filters['location'] : '', 'os' => $filters['os'] ?? '',
            ],
            'filterOptions' => [
                'departments' => (clone $assignedAssets)->with('currentAssignment:id,asset_id,department')->get()->pluck('currentAssignment.department')->filter()->unique()->sort()->values(),
                'categories' => Category::query()->whereIn('type', ['asset', 'both'])->orderBy('name')->get(['id', 'name']),
                'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
                'operatingSystems' => (clone $assignedAssets)->whereNotNull('operating_system')->where('operating_system', '<>', '')->distinct()->orderBy('operating_system')->pluck('operating_system'),
            ],
        ]);
    }

    public function repairs(Request $request): Response
    {
        $this->authorizeRead($request);
        return Inertia::render('ItAssets/Section', [
            'title' => 'Repairs', 'description' => 'IT assets currently recorded as under repair.',
            'rows' => Asset::where('current_status', AssetStatus::UnderRepair->value)->orderBy('asset_tag_no')->get()->map(fn ($asset) => [
                'asset_tag' => $asset->asset_tag_no, 'detail' => $asset->model ?: $asset->description, 'meta' => 'Under repair',
            ]),
        ]);
    }

    public function reports(Request $request): Response
    {
        $this->authorizeRead($request);
        return Inertia::render('ItAssets/Section', [
            'title' => 'IT Asset Reports', 'description' => 'Branch-scoped IT asset totals and reporting entry point.',
            'stats' => Asset::selectRaw('current_status, COUNT(*) as total')->groupBy('current_status')->get()->map(fn ($row) => [
                'label' => str($row->current_status->value)->replace('_', ' ')->title()->toString(), 'value' => $row->total,
            ]),
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);
    }
}
