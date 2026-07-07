<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CogApprovalController;
use App\Http\Controllers\CogController;
use App\Http\Controllers\QuickSearchController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AssetLedgerController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\StockAnomalyController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/cog/approval/{token}', [CogApprovalController::class, 'show'])->name('cogs.approval.show');
Route::post('/cog/approval/{token}/approve', [CogApprovalController::class, 'approve'])->name('cogs.approval.approve');
Route::post('/cog/approval/{token}/reject', [CogApprovalController::class, 'reject'])->name('cogs.approval.reject');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/quick-search', QuickSearchController::class)->name('quick-search');
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
