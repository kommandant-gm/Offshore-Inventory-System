<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ItLicense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json([
                'items' => [],
            ]);
        }

        $results = collect();

        if ($user?->canRead('assets')) {
            $itemResults = InventoryItem::query()
                ->with(['category', 'defaultLocation'])
                ->where(function ($builder) use ($query) {
                    $builder->where('item_code', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->orderBy('item_code')
                ->limit(5)
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'id' => "item-{$item->id}",
                    'type' => 'Stock Item',
                    'title' => $item->item_code,
                    'subtitle' => trim(collect([
                        $item->description,
                        $item->defaultLocation?->name,
                    ])->filter()->implode(' • ')),
                    'href' => route('assets.show', $item),
                ]);

            $results = $results->concat($itemResults);
        }

        if ($user?->canRead('movements')) {
            $movementResults = InventoryTransaction::query()
                ->with(['item', 'sourceLocation', 'destinationLocation'])
                ->whereHas('item', function ($builder) use ($query) {
                    $builder->where('item_code', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->latest('transaction_date')
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn (InventoryTransaction $transaction) => [
                    'id' => "movement-{$transaction->id}",
                    'type' => 'Movement',
                    'title' => $transaction->item?->item_code ?? 'Movement',
                    'subtitle' => trim(collect([
                        $transaction->transaction_type->value,
                        $transaction->sourceLocation?->name,
                        $transaction->destinationLocation?->name,
                    ])->filter()->implode(' • ')),
                    'href' => route('asset-movements.index'),
                ]);

            $results = $results->concat($movementResults);
        }

        if ($user?->canRead('it_assets')) {
            $licenseResults = ItLicense::query()
                ->where(function ($builder) use ($query) {
                    $builder->where('license_code', 'like', "%{$query}%")
                        ->orWhere('software_name', 'like', "%{$query}%")
                        ->orWhere('vendor', 'like', "%{$query}%");
                })
                ->orderBy('software_name')
                ->limit(5)
                ->get()
                ->map(fn (ItLicense $license) => [
                    'id' => "license-{$license->id}",
                    'type' => 'IT Licence',
                    'title' => $license->software_name,
                    'subtitle' => collect([$license->license_code, $license->vendor])->filter()->implode(' • '),
                    'href' => route('it-licenses.show', $license),
                ]);

            $results = $results->concat($licenseResults);
        }

        return response()->json([
            'items' => $results->take(8)->values()->all(),
        ]);
    }
}
