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
        return Inertia::render('ItAssets/Section', [
            'title' => 'IT Dashboard', 'description' => 'KL IT asset overview and lifecycle status.',
            'stats' => [
                ['label' => 'Total assets', 'value' => Asset::count()],
                ['label' => 'Assigned', 'value' => Asset::where('current_status', AssetStatus::Deployed->value)->count()],
                ['label' => 'Available', 'value' => Asset::where('current_status', AssetStatus::Available->value)->count()],
                ['label' => 'Under repair', 'value' => Asset::where('current_status', AssetStatus::UnderRepair->value)->count()],
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
