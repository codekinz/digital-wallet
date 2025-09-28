<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessTransferJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $senderId,
        protected int $receiverId,
        protected float $amount
    ) {
        //
    }

    /**
     * @throws Exception|Throwable
     */
    public function handle(): void
    {
        try {
            $sender = User::findOrFail($this->senderId);
            $receiver = User::findOrFail($this->receiverId);

            $commission = $this->amount * 0.015;
            $totalDebit = $this->amount + $commission;

            if ($sender->balance < $totalDebit) {
                throw new Exception('Balance not enough');
            }

            DB::beginTransaction();

            $sender->balance -= $totalDebit;
            $receiver->balance += $this->amount;
            $sender->save();
            $receiver->save();

            Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $this->amount,
                'commission_fee' => $commission
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
