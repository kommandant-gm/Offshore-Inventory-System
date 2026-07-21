<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CogApprovalController;
use App\Http\Controllers\CogController;
use App\Http\Controllers\QuickSearchController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AssetLedgerController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\StockAnomalyController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryImportController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetQrCodeController;
use App\Http\Controllers\PublicAssetController;
use App\Http\Controllers\ItAssetImportController;
use App\Http\Controllers\ItAssetSectionController;
use App\Http\Controllers\IssueLogController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/cog/approval/{token}', [CogApprovalController::class, 'show'])->name('cogs.approval.show');
Route::post('/cog/approval/{token}/approve', [CogApprovalController::class, 'approve'])->name('cogs.approval.approve');
Route::post('/cog/approval/{token}/reject', [CogApprovalController::class, 'reject'])->name('cogs.approval.reject');
Route::get('/asset/{publicToken}', PublicAssetController::class)->name('public.it-assets.show');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::patch('/active-branch', [BranchContextController::class, 'update'])->name('branches.activate');
    Route::resource('it-assets', AssetController::class)->parameters(['it-assets' => 'asset'])->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/it-assets/{asset}/checkout', [AssetAssignmentController::class, 'store'])->name('it-assets.checkout');
    Route::patch('/it-assets/{asset}/check-in', [AssetAssignmentController::class, 'destroy'])->name('it-assets.check-in');
    Route::get('/it-assets/{asset}/qr-code', [AssetQrCodeController::class, 'show'])->name('it-assets.qr-code.show');
    Route::post('/it-assets/{asset}/qr-code', [AssetQrCodeController::class, 'store'])->name('it-assets.qr-code.store');
    Route::post('/it-assets/{asset}/qr-code/regenerate', [AssetQrCodeController::class, 'update'])->name('it-assets.qr-code.regenerate');
    Route::get('/it-assets-import', [ItAssetImportController::class, 'create'])->name('it-assets.import.create');
    Route::post('/it-assets-import/preview', [ItAssetImportController::class, 'preview'])->name('it-assets.import.preview');
    Route::post('/it-assets-import', [ItAssetImportController::class, 'store'])->name('it-assets.import.store');
    Route::get('/it-dashboard', [ItAssetSectionController::class, 'dashboard'])->name('it-assets.dashboard');
    Route::get('/it-asset-assignments', [ItAssetSectionController::class, 'assignments'])->name('it-assets.assignments');
    Route::get('/it-asset-repairs', [ItAssetSectionController::class, 'repairs'])->name('it-assets.repairs');
    Route::get('/it-asset-reports', [ItAssetSectionController::class, 'reports'])->name('it-assets.reports');
    Route::get('/quick-search', QuickSearchController::class)->name('quick-search');
    Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/assistant/query', [AssistantController::class, 'query'])->name('assistant.query');
    Route::get('/stock-anomalies', [StockAnomalyController::class, 'index'])->name('anomalies.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/users/{user}/access', [SettingsController::class, 'updateUserAccess'])->name('settings.users.update');
    Route::get('/settings/issue-logs', [IssueLogController::class, 'index'])->name('settings.issue-logs.index');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');

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
    Route::get('/assets/import', [InventoryImportController::class, 'create'])->name('assets.import.create');
    Route::post('/assets/import', [InventoryImportController::class, 'store'])->name('assets.import.store');
    Route::post('/assets', [InventoryItemController::class, 'store'])->name('assets.store');
    Route::get('/assets/{item}', [InventoryItemController::class, 'show'])->name('assets.show');
    Route::patch('/assets/{item}', [InventoryItemController::class, 'update'])->name('assets.update');

    Route::get('/asset-movements', [InventoryTransactionController::class, 'index'])->name('asset-movements.index');
    Route::get('/asset-movements/create', [InventoryTransactionController::class, 'create'])->name('asset-movements.create');
    Route::post('/asset-movements', [InventoryTransactionController::class, 'store'])->name('asset-movements.store');
    Route::get('/stocktakes', [StocktakeController::class, 'index'])->name('stocktakes.index');
    Route::get('/stocktakes/create', [StocktakeController::class, 'create'])->name('stocktakes.create');
    Route::post('/stocktakes', [StocktakeController::class, 'store'])->name('stocktakes.store');
    Route::get('/stocktakes/{stocktake}', [StocktakeController::class, 'show'])->name('stocktakes.show');
    Route::get('/asset-ledger', [AssetLedgerController::class, 'index'])->name('asset-ledger.index');
});

require __DIR__.'/auth.php';
