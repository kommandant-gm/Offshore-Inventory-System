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

        $attentionItems = InventoryItem::query()
            ->with([
                'category',
                'defaultLocation',
                'locationBalances.location',
            ])
            ->where('active', true)
            ->get()
            ->map(function (InventoryItem $item) use ($itemProjector) {
                $currentStock = $itemProjector->currentStock($item);
                $minimumStock = $item->minimum_stock !== null ? (float) $item->minimum_stock : null;
                $stockGap = $minimumStock !== null ? $minimumStock - $currentStock : null;

                return [
                    ...$itemProjector->listPayload($item),
                    'stock_gap' => $stockGap,
                ];
            })
            ->filter(fn (array $item) => $item['stock_gap'] !== null && $item['stock_gap'] > 0)
            ->sortByDesc(fn (array $item) => $item['stock_gap'])
            ->take(4)
            ->values()
            ->map(fn (array $item) => collect($item)->except('stock_gap')->all());

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
