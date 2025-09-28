<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/api/transactions', [TransactionController::class, 'index']);
    Route::post('/api/transactions', [TransactionController::class, 'store'])->name('transactions.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
