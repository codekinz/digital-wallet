<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use App\Jobs\ProcessTransferJob;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $page = $request->get('page', 1);

        $transactions = Cache::tags(["user_transactions:{$user->id}"])
            ->remember("page_{$page}", 300, function () use ($user) {
                return Transaction::query()->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id)
                    ->with(['sender', 'receiver'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
            });

        return response()->json([
            'transactions' => $transactions,
            'balance' => $user->balance
        ]);
    }

    public function store(TransferRequest $request)
    {
        ProcessTransferJob::dispatch(
            auth()->id(),
            $request->receiver_id,
            (float) $request->amount
        );
    }
}
