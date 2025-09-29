<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Events\TransactionCreated;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $page = $request->get('page', 1);

        $transactions = Cache::remember(
            "user_transactions:{$user->id}:page_{$page}",
            300,
            function () use ($user) {
                return Transaction::query()
                    ->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id)
                    ->with(['sender', 'receiver'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
            }
        );

        return Inertia::render('Dashboard', [
            'transactions' => $transactions,
            'balance' => $user->balance,
            'filters' => $request->only(['page']),
        ]);
    }

    public function store(Request $request)
{
    $request->validate([
        'receiver_id' => 'required|exists:users,id',
        'amount' => 'required|numeric|min:0.01|max:'.auth()->user()->balance,
    ]);

    $amount = $request->amount;
    $commissionFee = $amount * 0.015;

    DB::beginTransaction();

    try {
        // Update sender's balance first
        $sender = auth()->user();
        $sender->balance -= $amount;
        $sender->save();

        // Update receiver's balance
        $receiver = User::findOrFail($request->receiver_id);
        $receiver->balance += $amount;
        $receiver->save();

        // Create the transaction
        $transaction = Transaction::create([
            'sender_id' => $sender->id,
            'receiver_id' => $request->receiver_id,
            'amount' => $amount,
            'commission_fee' => $commissionFee,
        ]);

        DB::commit();

        // Clear cache
        Cache::forget("user_transactions:{$sender->id}:page_1");
        Cache::forget("user_transactions:{$receiver->id}:page_1");

        \Log::info("Creating TransactionCreated event for transaction #{$transaction->id}");

        // Fire the event
        event(new TransactionCreated($transaction));

        \Log::info("TransactionCreated event fired for transaction #{$transaction->id}");

        return redirect()->route('dashboard')->with([
            'success' => 'Transaction completed successfully!',
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error("Transaction failed: " . $e->getMessage());
        return back()->withErrors([
            'error' => 'Transaction failed: ' . $e->getMessage()
        ]);
    }
}
}