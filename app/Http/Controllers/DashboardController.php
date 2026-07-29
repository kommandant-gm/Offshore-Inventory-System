<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Support\InventoryItemProjector;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(InventoryItemProjector $itemProjector): Response
    {
        abort_unless(request()->user()?->canRead('dashboard'), 403);

        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(6);
        $weekStartDate = $weekStart->toDateString();
        $todayDate = $today->toDateString();

        $latestTransactions = InventoryTransaction::query()
            ->with(['item', 'sourceLocation', 'destinationLocation', 'location', 'creator'])
            ->latest('transaction_date')
            ->latest('id')
            ->take(5)
            ->get();

        $dailyCounts = InventoryTransaction::query()
            ->selectRaw('DATE(transaction_date) as movement_day, COUNT(*) as total')
            ->whereBetween('transaction_date', [$weekStartDate, $todayDate])
            ->groupBy('movement_day')
            ->pluck('total', 'movement_day');

        $weeklyActivity = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $dailyCounts) {
            $date = $weekStart->copy()->addDays($offset);
            $key = $date->format('Y-m-d');

            return [
                'label' => $date->format('D'),
                'count' => (int) ($dailyCounts[$key] ?? 0),
            ];
        });

        $movementMix = InventoryTransaction::query()
            ->select('transaction_type', DB::raw('COUNT(*) as total'))
            ->groupBy('transaction_type')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(fn ($transaction) => [
                'type' => $transaction->transaction_type->value,
                'label' => str($transaction->transaction_type->value)->replace('_', ' ')->title()->value(),
                'total' => $transaction->total,
            ]);

        $inventoryItems = InventoryItem::query()
            ->with([
                'category',
                'defaultLocation',
                'locationBalances.location',
            ])
            ->where('active', true)
            ->get();

        $inventoryRows = $inventoryItems->map(function (InventoryItem $item) use ($itemProjector) {
            $currentStock = $itemProjector->currentStock($item);
            $minimumStock = $item->minimum_stock !== null ? (float) $item->minimum_stock : null;

            return [
                ...$itemProjector->listPayload($item),
                'category_id' => $item->category_id,
                'stock_gap' => $minimumStock !== null ? $minimumStock - $currentStock : null,
            ];
        });

        $lowStockItems = $inventoryRows
            ->filter(fn (array $item) => $item['stock_gap'] !== null && $item['stock_gap'] > 0)
            ->sortByDesc(fn (array $item) => $item['stock_gap']);

        $attentionItems = $lowStockItems
            ->take(4)
            ->values()
            ->map(fn (array $item) => collect($item)->except('stock_gap')->all());

        $inStockItems = $inventoryRows->filter(fn (array $item) => (float) $item['current_stock'] > 0);
        $outOfStockItems = $inventoryRows->filter(fn (array $item) => (float) $item['current_stock'] <= 0);
        $healthyItems = $inStockItems->reject(fn (array $item) => $lowStockItems->contains('id', $item['id']));
        $totalStockQuantity = round((float) $inventoryRows->sum('current_stock'), 2);
        $totalInventoryValue = round((float) $inventoryRows->sum(fn (array $item) => (float) $item['current_stock'] * (float) $item['standard_cost']), 2);

        $categoryDistribution = $inventoryRows
            ->groupBy(fn (array $item) => $item['category'] ?: 'Uncategorised')
            ->map(function ($items, string $label) {
                return [
                    'key' => $items->first()['category_id'],
                    'label' => $label,
                    'value' => $items->count(),
                    'quantity' => round((float) $items->sum('current_stock'), 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->take(10);

        $locationDistribution = $inventoryItems
            ->flatMap(function (InventoryItem $item) use ($itemProjector) {
                $balances = $item->locationBalances
                    ->filter(fn ($balance) => (float) $balance->quantity != 0.0)
                    ->map(fn ($balance) => [
                        'item_id' => $item->id,
                        'label' => $balance->location?->name ?? 'Unassigned',
                        'quantity' => (float) $balance->quantity,
                    ]);

                if ($balances->isNotEmpty()) {
                    return $balances;
                }

                $currentStock = $itemProjector->currentStock($item);

                return $currentStock != 0.0 ? [[
                    'item_id' => $item->id,
                    'label' => $item->defaultLocation?->name ?? 'Unassigned',
                    'quantity' => $currentStock,
                ]] : [];
            })
            ->groupBy('label')
            ->map(fn ($balances, string $label) => [
                'label' => $label,
                'value' => $balances->pluck('item_id')->unique()->count(),
                'quantity' => round((float) $balances->sum('quantity'), 2),
            ])
            ->sortByDesc('quantity')
            ->values()
            ->take(8);

        $featuredMovement = $latestTransactions->first();

        $cogTransactions = InventoryTransaction::query()
            ->with('item')
            ->where(function ($query) {
                $query->whereNotNull('cog_issued_out')
                    ->where('cog_issued_out', '!=', '')
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNotNull('cog_received')
                            ->where('cog_received', '!=', '');
                    });
            })
            ->latest('transaction_date')
            ->latest('id')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'assetItems' => InventoryItem::count(),
                'assetTransactions' => InventoryTransaction::count(),
                'categories' => Category::count(),
                'locations' => Location::count(),
            ],
            'inventorySummary' => [
                'active_items' => $inventoryRows->count(),
                'in_stock' => $inStockItems->count(),
                'healthy' => $healthyItems->count(),
                'low_stock' => $lowStockItems->count(),
                'out_of_stock' => $outOfStockItems->count(),
                'total_quantity' => $totalStockQuantity,
                'total_value' => $totalInventoryValue,
            ],
            'stockStatus' => [
                ['key' => 'healthy', 'label' => 'Healthy Stock', 'value' => $healthyItems->count()],
                ['key' => 'low', 'label' => 'Low Stock', 'value' => $lowStockItems->count()],
                ['key' => 'out', 'label' => 'Out of Stock', 'value' => $outOfStockItems->count()],
            ],
            'categoryDistribution' => $categoryDistribution,
            'locationDistribution' => $locationDistribution,
            'featuredMovement' => $featuredMovement ? [
                'item_code' => $featuredMovement->item?->item_code,
                'description' => $featuredMovement->item?->description,
                'transaction_type' => $featuredMovement->transaction_type->value,
                'transaction_date' => $featuredMovement->transaction_date->format('Y-m-d'),
                'quantity' => $featuredMovement->quantity,
                'total_value' => $featuredMovement->total_value,
                'source_location' => $featuredMovement->sourceLocation?->name ?? $featuredMovement->location?->name,
                'destination_location' => $featuredMovement->destinationLocation?->name ?? $featuredMovement->location?->name,
                'created_by' => $featuredMovement->creator?->name,
            ] : null,
            'recentMovements' => $latestTransactions->map(fn (InventoryTransaction $transaction) => [
                'id' => $transaction->id,
                'item_code' => $transaction->item?->item_code,
                'description' => $transaction->item?->description,
                'transaction_type' => $transaction->transaction_type->value,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'quantity' => $transaction->quantity,
                'total_value' => $transaction->total_value,
                'source_location' => $transaction->sourceLocation?->name ?? $transaction->location?->name,
                'destination_location' => $transaction->destinationLocation?->name ?? $transaction->location?->name,
                'created_by' => $transaction->creator?->name,
            ]),
            'movementMix' => $movementMix,
            'weeklyActivity' => $weeklyActivity,
            'attentionItems' => $attentionItems,
            'cogSummary' => [
                'issuedCount' => InventoryTransaction::query()
                    ->whereNotNull('cog_issued_out')
                    ->where('cog_issued_out', '!=', '')
                    ->count(),
                'receivedCount' => InventoryTransaction::query()
                    ->whereNotNull('cog_received')
                    ->where('cog_received', '!=', '')
                    ->count(),
            ],
            'cogEntries' => $cogTransactions->map(fn (InventoryTransaction $transaction) => [
                'id' => $transaction->id,
                'item_code' => $transaction->item?->item_code,
                'description' => $transaction->item?->description,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'transaction_type' => $transaction->transaction_type->value,
                'cog_issued_out' => $transaction->cog_issued_out,
                'cog_received' => $transaction->cog_received,
                'total_value' => $transaction->total_value,
            ]),
            'systemHealth' => [
                'movementCountToday' => InventoryTransaction::query()->where('transaction_date', $todayDate)->count(),
                'activeItems' => InventoryItem::query()->where('active', true)->count(),
            ],
        ]);
    }
}
