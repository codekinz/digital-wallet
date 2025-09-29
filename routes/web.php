<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard route with transactions data
    Route::get('/dashboard', [TransactionController::class, 'index'])->name('dashboard');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
});

// Pusher authentication endpoint
Route::post('/pusher/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware('auth');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
