<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoinService
{
    /**
     * Award coins to a user atomically and record the transaction.
     */
    public function award(User $user, int $amount, CoinTransactionType $type, ?Model $reference = null): CoinTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Award amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $reference): CoinTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $transaction = CoinTransaction::create([
                'user_id' => $lockedUser->id,
                'amount' => $amount,
                'type' => $type,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
            ]);

            $lockedUser->coin_balance += $amount;
            $lockedUser->save();

            // Sync original user instance if same
            $user->coin_balance = $lockedUser->coin_balance;

            return $transaction;
        });
    }

    /**
     * Spend coins from a user atomically and record the transaction.
     */
    public function spend(User $user, int $amount, CoinTransactionType $type, ?Model $reference = null): CoinTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Spend amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $reference): CoinTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->coin_balance < $amount) {
                throw new InvalidArgumentException('Insufficient coin balance.');
            }

            $transaction = CoinTransaction::create([
                'user_id' => $lockedUser->id,
                'amount' => -$amount,
                'type' => $type,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
            ]);

            $lockedUser->coin_balance -= $amount;
            $lockedUser->save();

            // Sync original user instance
            $user->coin_balance = $lockedUser->coin_balance;

            return $transaction;
        });
    }

    /**
     * Get the current balance of a user.
     */
    public function getBalance(User $user): int
    {
        return (int) $user->refresh()->coin_balance;
    }
}
