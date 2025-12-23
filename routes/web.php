<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// --- ADD THIS SECTION ---
Route::get('/yard-locations', function () {
    return Inertia::render('YardLocation'); 
})->middleware(['auth', 'verified'])->name('yard.locations');
// ------------------------

Route::get('/movements', function () {
    return Inertia::render('Movements'); 
})->middleware(['auth', 'verified'])->name('movements');

Route::get('/asset-master', function () {
    return Inertia::render('AssetMaster');
})->middleware(['auth', 'verified'])->name('asset.master');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
