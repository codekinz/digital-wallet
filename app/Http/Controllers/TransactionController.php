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
        \Log::info("Transaction store method called", [
            'receiver_id' => $request->receiver_id,
            'amount' => $request->amount,
            'user_id' => auth()->id()
        ]);

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

            \Log::info("Sender balance updated", [
                'sender_id' => $sender->id,
                'new_balance' => $sender->balance
            ]);

            // Update receiver's balance
            $receiver = User::findOrFail($request->receiver_id);
            $receiver->balance += $amount;
            $receiver->save();

            \Log::info("Receiver balance updated", [
                'receiver_id' => $receiver->id,
                'new_balance' => $receiver->balance
            ]);

            // Create the transaction
            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $request->receiver_id,
                'amount' => $amount,
                'commission_fee' => $commissionFee,
            ]);

            \Log::info("Transaction created", [
                'transaction_id' => $transaction->id,
                'sender_id' => $transaction->sender_id,
                'receiver_id' => $transaction->receiver_id,
                'amount' => $transaction->amount
            ]);

            DB::commit();

            // Clear cache
            Cache::forget("user_transactions:{$sender->id}:page_1");
            Cache::forget("user_transactions:{$receiver->id}:page_1");

            \Log::info("BEFORE broadcasting TransactionCreated event");

            // Fire the event - try different methods
            try {
                // Method 1: Using event helper
                event(new TransactionCreated($transaction));
                \Log::info("Event fired using event() helper");
            } catch (\Exception $e) {
                \Log::error("event() helper failed: " . $e->getMessage());
            }

            try {
                // Method 2: Using broadcast helper
                broadcast(new TransactionCreated($transaction));
                \Log::info("Event fired using broadcast() helper");
            } catch (\Exception $e) {
                \Log::error("broadcast() helper failed: " . $e->getMessage());
            }

            try {
                // Method 3: Using static dispatch
                TransactionCreated::dispatch($transaction);
                \Log::info("Event fired using static dispatch");
            } catch (\Exception $e) {
                \Log::error("Static dispatch failed: " . $e->getMessage());
            }

            \Log::info("🎯 AFTER all event firing attempts");

            return redirect()->route('dashboard')->with([
                'success' => 'Transaction completed successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Transaction failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors([
                'error' => 'Transaction failed: ' . $e->getMessage()
            ]);
        }
    }
}
