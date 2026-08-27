<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class XpService
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Award experience points to a user atomically, and automatically convert whole 1,500-XP chunks to coins.
     *
     * @return array{
     *     xp_awarded: int,
     *     experience_points: int,
     *     coins_converted: int,
     *     coin_balance: int
     * }
     */
    public function award(User $user, int $amount, ?Model $reference = null): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('XP award amount cannot be negative.');
        }

        if ($amount === 0) {
            return [
                'xp_awarded' => 0,
                'experience_points' => (int) $user->experience_points,
                'coins_converted' => 0,
                'coin_balance' => (int) $user->coin_balance,
            ];
        }

        return DB::transaction(function () use ($user, $amount, $reference): array {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $lockedUser->experience_points += $amount;
            $lockedUser->save();

            // Check if user crossed one or more 1,500 XP conversion thresholds (1,500 XP = 50 coins)
            $availableXp = max(0, $lockedUser->experience_points - $lockedUser->xp_converted_total);
            $chunks = intdiv($availableXp, 1500);
            $coinsEarned = 0;

            if ($chunks > 0) {
                $xpToConvert = $chunks * 1500;
                $coinsEarned = $chunks * 50;

                $lockedUser->xp_converted_total += $xpToConvert;
                $lockedUser->save();

                $this->coinService->award(
                    $lockedUser,
                    $coinsEarned,
                    CoinTransactionType::EarnedXpConversion,
                    $reference ?? $lockedUser
                );
            }

            // Sync user object passed by reference/caller
            $user->experience_points = $lockedUser->experience_points;
            $user->xp_converted_total = $lockedUser->xp_converted_total;
            $user->coin_balance = $lockedUser->coin_balance;

            return [
                'xp_awarded' => $amount,
                'experience_points' => $lockedUser->experience_points,
                'coins_converted' => $coinsEarned,
                'coin_balance' => $lockedUser->coin_balance,
            ];
        });
    }

    /**
     * Get user's current lifetime experience points.
     */
    public function getXp(User $user): int
    {
        return (int) $user->refresh()->experience_points;
    }

    /**
     * Get user's unconverted experience points.
     */
    public function getUnconvertedXp(User $user): int
    {
        $user->refresh();

        return max(0, $user->experience_points - $user->xp_converted_total);
    }
}
