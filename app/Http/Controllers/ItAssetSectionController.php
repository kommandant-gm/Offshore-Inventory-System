<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItAssetSectionController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $this->authorizeRead($request);

        $assets = Asset::query()
            ->with(['category:id,name', 'currentLocation:id,name', 'currentAssignment'])
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

        $ageLabels = array_keys($ageBands);
        $assetAgeBand = function (Asset $asset) use ($currentYear, $ageLabels): string {
            $year = $asset->purchase_year ?: (is_numeric($asset->year) ? (int) $asset->year : null);
            $age = $year ? max(0, $currentYear - $year) : null;

            return $age === null ? $ageLabels[4]
                : ($age < 2 ? $ageLabels[0] : ($age < 5 ? $ageLabels[1] : ($age < 8 ? $ageLabels[2] : $ageLabels[3])));
        };

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
                'departments' => $assets
                    ->filter(fn (Asset $asset) => $asset->currentAssignment !== null)
                    ->countBy(fn (Asset $asset) => trim((string) $asset->currentAssignment?->department) ?: 'Unspecified')
                    ->sortDesc()
                    ->map(fn (int $total, string $name) => ['label' => $name, 'value' => $total])
                    ->values(),
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
            'dashboardAssets' => $assets->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'assetTag' => $asset->asset_tag_no,
                'detail' => $asset->model ?: $asset->description,
                'status' => str($asset->current_status->value)->replace('_', ' ')->title()->toString(),
                'category' => $asset->category?->name ?: 'Not specified',
                'location' => $asset->currentLocation?->name ?: 'Not specified',
                'holder' => $asset->currentAssignment?->assigned_to_name,
                'department' => $asset->currentAssignment
                    ? (trim((string) $asset->currentAssignment->department) ?: 'Unspecified')
                    : null,
                'condition' => $asset->current_condition?->value
                    ? str($asset->current_condition->value)->replace('_', ' ')->title()->toString()
                    : 'Not specified',
                'age' => $assetAgeBand($asset),
                'purchaseYear' => $asset->purchase_year ? (string) $asset->purchase_year : null,
            ])->values(),
        ]);
    }

    public function repairs(Request $request): Response
    {
        $this->authorizeRead($request);

        return Inertia::render('ItAssets/Section', [
            'title' => 'Repairs', 'description' => 'IT assets currently recorded as under repair.',
            'repairMode' => true,
            'rows' => Asset::query()
                ->where('current_status', AssetStatus::UnderRepair->value)
                ->with(['movements' => fn ($query) => $query->where('movement_type', 'send_for_repair')])
                ->orderBy('asset_tag_no')->get()->map(function (Asset $asset) {
                    $repair = $asset->movements->first();

                    return [
                        'asset_tag' => $asset->asset_tag_no,
                        'asset_id' => $asset->id,
                        'detail' => $asset->model ?: $asset->description,
                        'repair_date' => $repair?->movement_date?->format('Y-m-d'),
                        'handled_by' => $repair?->handled_by,
                        'reference_no' => $repair?->reference_no,
                        'remarks' => $repair?->remarks,
                    ];
                }),
            'repairableAssets' => Asset::query()
                ->whereIn('current_status', [
                    AssetStatus::Available->value,
                    AssetStatus::Damaged->value,
                    AssetStatus::InspectionHold->value,
                ])
                ->whereDoesntHave('currentAssignment')
                ->orderBy('asset_tag_no')
                ->get(['id', 'asset_tag_no', 'description', 'model'])
                ->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'label' => $asset->asset_tag_no.' - '.($asset->model ?: $asset->description),
                ]),
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);
    }
}
