<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;
    public $senderBalance;
    public $receiverBalance;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        
        // Load fresh balances
        $this->senderBalance = $transaction->sender->fresh()->balance;
        $this->receiverBalance = $transaction->receiver->fresh()->balance;
    }

    public function broadcastOn(): array
    {
        \Log::info("Broadcasting TransactionCreated to user.{$this->transaction->sender_id} and user.{$this->transaction->receiver_id}");
        
        return [
            new Channel("user.{$this->transaction->sender_id}"),
            new Channel("user.{$this->transaction->receiver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransactionCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction' => $this->transaction->load(['sender', 'receiver']),
            'sender_balance' => (float) $this->senderBalance,
            'receiver_balance' => (float) $this->receiverBalance,
        ];
    }
}