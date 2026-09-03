<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ItLicense;
use App\Models\KemamanInventoryItem;
use App\Models\MajorEquipment;
use App\Services\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function __invoke(Request $request, BranchContext $branchContext): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $branchCode = $branchContext->branch($user)?->code;
        $results = collect();

        if ($branchCode === 'MIRI' && $user?->canRead('assets')) {
            $results = $results->concat(MajorEquipment::query()
                ->where(fn ($builder) => $builder->where('tag_no', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('serial_no', 'like', "%{$query}%"))
                ->orderBy('tag_no')->limit(8)->get()
                ->map(fn (MajorEquipment $item) => [
                    'id' => "miri-equipment-{$item->id}", 'type' => 'Miri Equipment',
                    'title' => $item->tag_no ?: ($item->description ?: 'Equipment'),
                    'subtitle' => collect([$item->description, $item->current_location])->filter()->implode(' · '),
                    'href' => route('major-equipment.show', $item),
                ]));
        } elseif ($branchCode === 'KEMAMAN' && $user?->canRead('assets')) {
            $results = $results->concat(KemamanInventoryItem::query()
                ->where(fn ($builder) => $builder->where('tag_no', 'like', "%{$query}%")
                    ->orWhere('item_description', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%"))
                ->orderBy('tag_no')->limit(8)->get()
                ->map(fn (KemamanInventoryItem $item) => [
                    'id' => "kemaman-equipment-{$item->id}", 'type' => 'Kemaman Equipment',
                    'title' => $item->tag_no ?: ($item->item_description ?: 'Equipment'),
                    'subtitle' => collect([$item->category, $item->location])->filter()->implode(' · '),
                    'href' => route('kemaman-inventory.index'),
                ]));
        } elseif ($branchCode === 'KL-IT' && $user?->canRead('it_assets')) {
            $results = $results->concat(Asset::query()->with('currentLocation')
                ->where(fn ($builder) => $builder->where('asset_tag_no', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('serial_no', 'like', "%{$query}%"))
                ->orderBy('asset_tag_no')->limit(8)->get()
                ->map(fn (Asset $asset) => [
                    'id' => "it-asset-{$asset->id}", 'type' => 'IT Asset', 'title' => $asset->asset_tag_no,
                    'subtitle' => collect([$asset->description, $asset->currentLocation?->name])->filter()->implode(' · '),
                    'href' => route('it-assets.show', $asset),
                ]));

            $results = $results->concat(ItLicense::query()
                ->where(fn ($builder) => $builder->where('license_code', 'like', "%{$query}%")
                    ->orWhere('software_name', 'like', "%{$query}%")
                    ->orWhere('vendor', 'like', "%{$query}%"))
                ->orderBy('software_name')->limit(5)->get()
                ->map(fn (ItLicense $license) => [
                    'id' => "license-{$license->id}", 'type' => 'IT Licence', 'title' => $license->software_name,
                    'subtitle' => collect([$license->license_code, $license->vendor])->filter()->implode(' · '),
                    'href' => route('it-licenses.show', $license),
                ]));
        }

        return response()->json(['items' => $results->take(8)->values()->all()]);
    }
}
