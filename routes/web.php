<?php

use App\Domain\Inventory\InventoryBalance;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CogApprovalController;
use App\Http\Controllers\CogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AssetLedgerController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\StockAnomalyController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/cog/approval/{token}', [CogApprovalController::class, 'show'])->name('cogs.approval.show');
Route::post('/cog/approval/{token}/approve', [CogApprovalController::class, 'approve'])->name('cogs.approval.approve');
Route::post('/cog/approval/{token}/reject', [CogApprovalController::class, 'reject'])->name('cogs.approval.reject');

Route::get('/dashboard', function () {
    abort_unless(request()->user()?->canRead('dashboard'), 403);

    $today = Carbon::today();
    $weekStart = $today->copy()->subDays(6);

    $latestTransactions = InventoryTransaction::query()
        ->with(['item', 'sourceLocation', 'destinationLocation', 'location', 'creator'])
        ->latest('transaction_date')
        ->latest('id')
        ->take(5)
        ->get();

    $dailyCounts = InventoryTransaction::query()
        ->selectRaw('DATE(transaction_date) as movement_day, COUNT(*) as total')
        ->whereDate('transaction_date', '>=', $weekStart)
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
        ->with(['category', 'defaultLocation'])
        ->orderBy('description')
        ->take(4)
        ->get()
        ->map(function (InventoryItem $item) {
            $currentStock = round((float) $item->opening_stock + InventoryBalance::currentQuantity($item), 2);

            return [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'category' => $item->category?->name,
                'location' => $item->defaultLocation?->name,
                'opening_stock' => $item->opening_stock,
                'current_stock' => $currentStock,
                'minimum_stock' => $item->minimum_stock,
            ];
        });

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
            'movementCountToday' => InventoryTransaction::query()->whereDate('transaction_date', $today)->count(),
            'activeItems' => InventoryItem::query()->where('active', true)->count(),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/assistant/query', [AssistantController::class, 'query'])->name('assistant.query');
    Route::get('/stock-anomalies', [StockAnomalyController::class, 'index'])->name('anomalies.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/users/{user}/access', [SettingsController::class, 'updateUserAccess'])->name('settings.users.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update']);
    Route::get('/cogs', [CogController::class, 'index'])->name('cogs.index');
    Route::get('/cogs/create', [CogController::class, 'create'])->name('cogs.create');
    Route::post('/cogs', [CogController::class, 'store'])->name('cogs.store');
    Route::get('/cogs/{cog}', [CogController::class, 'show'])->name('cogs.show');

    Route::get('/assets', [InventoryItemController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [InventoryItemController::class, 'create'])->name('assets.create');
    Route::post('/assets', [InventoryItemController::class, 'store'])->name('assets.store');
    Route::get('/assets/{item}', [InventoryItemController::class, 'show'])->name('assets.show');
    Route::patch('/assets/{item}', [InventoryItemController::class, 'update'])->name('assets.update');

    Route::get('/asset-movements', [InventoryTransactionController::class, 'index'])->name('asset-movements.index');
    Route::get('/asset-movements/create', [InventoryTransactionController::class, 'create'])->name('asset-movements.create');
    Route::post('/asset-movements', [InventoryTransactionController::class, 'store'])->name('asset-movements.store');
    Route::get('/asset-ledger', [AssetLedgerController::class, 'index'])->name('asset-ledger.index');
});

require __DIR__.'/auth.php';
