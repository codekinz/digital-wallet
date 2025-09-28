<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $transactions = Transaction::where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['sender', 'receiver'])
            ->get();
        return response()->json([
            'transactions' => $transactions,
            'balance' => $user->balance
        ]);
    }

    public function store(TransferRequest $request)
    {
        $sender = auth()->user();
        $receiver = User::findOrFail($request->receiver_id);
        $amount = $request->amount;
        $commission = $amount * 0.015;
        $totalDebit = $amount + $commission;

        if ($sender->balance < $totalDebit) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        DB::beginTransaction();
        try {
            $sender->balance -= $totalDebit;
            $receiver->balance += $amount;
            $sender->save();
            $receiver->save();

            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $amount,
                'commission_fee' => $commission
            ]);

            DB::commit();
            return response()->json($transaction, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
