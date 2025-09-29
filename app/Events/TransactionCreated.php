<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pusher\Pusher;

class TransactionCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public float $senderBalance,
        public float $receiverBalance
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('public-user.' . $this->transaction->sender_id),
            new Channel('public-user.' . $this->transaction->receiver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransactionCreated';
    }

    public function broadcastWith(): array
    {
        $sender = User::findOrFail($this->transaction->sender_id);
        $receiver = User::findOrFail($this->transaction->receiver_id);

        $data = [
            'transaction' => [
                'id' => $this->transaction->id,
                'sender_id' => $this->transaction->sender_id,
                'receiver_id' => $this->transaction->receiver_id,
                'amount' => (float) $this->transaction->amount,
                'commission_fee' => (float) $this->transaction->commission_fee,
                'created_at' => $this->transaction->created_at->toIso8601String(),
                'sender' => [
                    'id' => $sender->id,
                    'name' => $sender->name,
                ],
                'receiver' => [
                    'id' => $receiver->id,
                    'name' => $receiver->name,
                ],
            ],
            'sender_balance' => (float) $this->senderBalance,
            'receiver_balance' => (float) $this->receiverBalance,
        ];

        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            ['cluster' => env('PUSHER_APP_CLUSTER'), 'useTLS' => false]
        );
        $pusher->trigger(
            ['public-user.' . $this->transaction->sender_id, 'public-user.' . $this->transaction->receiver_id],
            'TransactionCreated',
            $data
        );

        return $data;
    }
}
