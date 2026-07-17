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
        return Inertia::render('ItAssets/Section', [
            'title' => 'Assignments / Returns', 'description' => 'Current device custody and assignment records.',
            'rows' => Asset::with('currentAssignment')->whereHas('currentAssignment')->orderBy('asset_tag_no')->get()->map(fn ($asset) => [
                'asset_tag' => $asset->asset_tag_no, 'detail' => $asset->currentAssignment?->assigned_to_name,
                'meta' => $asset->currentAssignment?->department,
            ]),
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
