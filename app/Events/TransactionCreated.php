<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $transaction;
    public $senderBalance;
    public $receiverBalance;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->senderBalance = $transaction->sender->fresh()->balance;
        $this->receiverBalance = $transaction->receiver->fresh()->balance;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('public-user-' . $this->transaction->sender_id),
            new Channel('public-user-' . $this->transaction->receiver_id),
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
