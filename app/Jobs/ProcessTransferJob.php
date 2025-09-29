<?php

namespace App\Jobs;

use App\Events\TransactionCreated;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessTransferJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        protected int $senderId,
        protected int $receiverId,
        protected float $amount
    ) {
    }

    public function handle(): void
    {
        $this->withOptimisticRetries(function () {
            $this->transfer();
        });
    }

    protected function withOptimisticRetries(
        callable $callback,
        int $maxRetries = 5,
        int $baseDelayMs = 50
    ): void {
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $callback();
                return;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'balance conflict')) {
                    if ($attempt === $maxRetries) {
                        logger("Optimistic lock failed after {$maxRetries} attempts: {$e->getMessage()}", [
                            'sender_id' => $this->senderId,
                            'receiver_id' => $this->receiverId,
                            'amount' => $this->amount,
                        ]);
                        throw $e;
                    }

                    logger("Optimistic lock retry {$attempt}/{$maxRetries}: {$e->getMessage()}", [
                        'sender_id' => $this->senderId,
                        'receiver_id' => $this->receiverId,
                    ]);

                    usleep($baseDelayMs * 1000 * (2 ** ($attempt - 1)));
                    $attempt++;
                } else {
                    logger("Transfer failed: {$e->getMessage()}", [
                        'sender_id' => $this->senderId,
                        'receiver_id' => $this->receiverId,
                        'amount' => $this->amount,
                    ]);
                    throw $e;
                }
            }
        }
    }

    private function transfer(): void
    {
        $commission = $this->amount * config('transactions.transaction_rate', 0.015);
        $totalDebit = $this->amount + $commission;

        DB::beginTransaction();

        $sender = User::findOrFail($this->senderId);
        $receiver = User::findOrFail($this->receiverId);

        if ($sender->balance < $totalDebit) {
            throw new Exception('Insufficient balance');
        }

        $senderLock = $sender->version;
        $receiverLock = $receiver->version;

        $updatedRows = DB::table('users')
            ->where('id', $this->senderId)
            ->where('version', $senderLock)
            ->update([
                'version' => DB::raw('version + 1'),
                'balance' => DB::raw("balance - {$totalDebit}"),
                'updated_at' => now(),
            ]);

        if ($updatedRows === 0) {
            throw new Exception("Sender balance conflict for ID {$this->senderId}, version {$senderLock}");
        }

        $updatedRows = DB::table('users')
            ->where('id', $this->receiverId)
            ->where('version', $receiverLock)
            ->update([
                'version' => DB::raw('version + 1'),
                'balance' => DB::raw("balance + {$this->amount}"),
                'updated_at' => now(),
            ]);

        if ($updatedRows === 0) {
            throw new Exception("Receiver balance conflict for ID {$this->receiverId}, version {$receiverLock}");
        }

        $transaction = Transaction::create([
            'sender_id' => $this->senderId,
            'receiver_id' => $this->receiverId,
            'amount' => $this->amount,
            'commission_fee' => $commission,
        ]);

        $newSenderBalance = $sender->balance - $totalDebit;
        $newReceiverBalance = $receiver->balance + $this->amount;

        cache()->forget("user_balance:{$this->senderId}");
        cache()->forget("user_balance:{$this->receiverId}");
        cache()->tags(["user_transactions:{$this->senderId}"])->flush();
        cache()->tags(["user_transactions:{$this->receiverId}"])->flush();

        event(new TransactionCreated($transaction, $newSenderBalance, $newReceiverBalance));

        DB::commit();
    }
}
