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


Route::get('/test-pusher-config', function () {
    try {
        // Test Pusher configuration
        $pusher = new Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
        
        // Test triggering an event
        $result = $pusher->trigger('test-channel', 'test-event', [
            'message' => 'Test from Laravel',
            'time' => now()->toISOString()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Pusher configuration is correct',
            'result' => $result
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Pusher configuration error: ' . $e->getMessage()
        ], 500);
    }
});


Route::get('/test-pusher-send', function () {
    \Log::info("Testing Pusher event sending");
    
    // Test using Laravel's broadcast helper
    try {
        broadcast(new \App\Events\TransactionCreated(\App\Models\Transaction::latest()->first()));
        \Log::info("✅ Broadcast test completed");
        return "Event broadcast attempted - check Pusher dashboard and Laravel logs";
    } catch (\Exception $e) {
        \Log::error("❌ Broadcast test failed: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
});