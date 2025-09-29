<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTransferJob;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $page = $request->get('page', 1);

        $transactions = cache()->tags(["user_transactions:{$user->id}"])
        ->remember(
            "user_transactions:{$user->id}:page_{$page}",
            300,
            function () use ($user) {
                return Transaction::query()
                    ->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id)
                    ->with(['sender' => fn ($q) => $q->select('id', 'name'), 'receiver' => fn ($q) => $q->select('id', 'name')])
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
        ProcessTransferJob::dispatch(
            auth()->id(),
            $request->receiver_id,
            (float) $request->amount
        );
    }
}
