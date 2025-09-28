<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Events\TransactionCreated;
use App\Http\Requests\TransferRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        return response()->json([
            'transactions' => $transactions,
            'balance' => $user->balance
        ]);
    }

    public function store(TransferRequest $request)
    {
        $sender = auth()->user();
        $receiver = User::findOrFail($request->receiver_id);

        $amount = (float) $request->amount;
        $commissionRate = config('transactions.transaction_rate');
        $commission = $amount * $commissionRate;
        $netAmount = $amount - $commission;

        DB::transaction(function () use ($sender, $receiver, $amount, $commission) {
            // Deduct from sender
            $sender->decrement('balance', $amount);

            // Add to receiver (net amount after commission)
            $receiver->increment('balance', $amount - $commission);

            // Store transaction
            $transaction = $sender->sentTransactions()->create([
                'receiver_id'   => $receiver->id,
                'amount'        => $amount,
                'commission_fee' => $commission,
            ]);

            // Fire event for real-time update
            broadcast(new TransactionCreated($transaction))->toOthers();
        });

        return redirect()->back()->with('success', 'Transaction successful');
    }
}
