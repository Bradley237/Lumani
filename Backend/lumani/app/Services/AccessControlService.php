<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Enums\SubscriptionStatus;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\PastPaper;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserChapterUnlock;
use App\Models\UserPastPaperUnlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessControlService
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Determine if a user can access a chapter.
     */
    public function canAccessChapter(User $user, Chapter $chapter): bool
    {
        if (AppSetting::isFreeModeEnabled()) {
            return true;
        }

        if ($chapter->is_free) {
            return true;
        }

        return UserChapterUnlock::where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();
    }

    /**
     * Unlock a chapter for a user.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     already_unlocked: bool,
     *     coins_spent: int,
     *     coin_balance: int
     * }
     */
    public function unlockChapter(User $user, Chapter $chapter): array
    {
        if ($this->canAccessChapter($user, $chapter)) {
            return [
                'success' => true,
                'message' => 'Chapter is already accessible.',
                'already_unlocked' => true,
                'coins_spent' => 0,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        }

        if ($user->coin_balance < $chapter->coin_price) {
            throw ValidationException::withMessages([
                'coins' => "Insufficient coin balance. Unlocking this chapter requires {$chapter->coin_price} coins.",
            ]);
        }

        return DB::transaction(function () use ($user, $chapter): array {
            $this->coinService->spend(
                $user,
                $chapter->coin_price,
                CoinTransactionType::SpentUnlock,
                $chapter
            );

            UserChapterUnlock::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'chapter_id' => $chapter->id,
                ],
                [
                    'unlocked_at' => now(),
                ]
            );

            return [
                'success' => true,
                'message' => "Chapter '{$chapter->title}' unlocked successfully.",
                'already_unlocked' => false,
                'coins_spent' => $chapter->coin_price,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        });
    }

    /**
     * Determine if a user can access a past paper.
     */
    public function canAccessPastPaper(User $user, PastPaper $pastPaper): bool
    {
        if (AppSetting::isFreeModeEnabled()) {
            return true;
        }

        return UserPastPaperUnlock::where('user_id', $user->id)
            ->where('past_paper_id', $pastPaper->id)
            ->whereNotNull('paper_unlocked_at')
            ->exists();
    }

    /**
     * Unlock a past paper for a user.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     already_unlocked: bool,
     *     coins_spent: int,
     *     coin_balance: int
     * }
     */
    public function unlockPastPaper(User $user, PastPaper $pastPaper): array
    {
        if ($this->canAccessPastPaper($user, $pastPaper)) {
            return [
                'success' => true,
                'message' => 'Past paper is already accessible.',
                'already_unlocked' => true,
                'coins_spent' => 0,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        }

        if ($user->coin_balance < $pastPaper->coin_price) {
            throw ValidationException::withMessages([
                'coins' => "Insufficient coin balance. Unlocking this past paper requires {$pastPaper->coin_price} coins.",
            ]);
        }

        return DB::transaction(function () use ($user, $pastPaper): array {
            $this->coinService->spend(
                $user,
                $pastPaper->coin_price,
                CoinTransactionType::SpentUnlock,
                $pastPaper
            );

            $unlock = UserPastPaperUnlock::firstOrNew([
                'user_id' => $user->id,
                'past_paper_id' => $pastPaper->id,
            ]);
            $unlock->paper_unlocked_at = now();
            $unlock->save();

            return [
                'success' => true,
                'message' => "Past paper '{$pastPaper->title}' unlocked successfully.",
                'already_unlocked' => false,
                'coins_spent' => $pastPaper->coin_price,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        });
    }

    /**
     * Determine if a user can access a past paper solution.
     */
    public function canAccessPastPaperSolution(User $user, PastPaper $pastPaper): bool
    {
        if (AppSetting::isFreeModeEnabled()) {
            return true;
        }

        return UserPastPaperUnlock::where('user_id', $user->id)
            ->where('past_paper_id', $pastPaper->id)
            ->whereNotNull('solution_unlocked_at')
            ->exists();
    }

    /**
     * Unlock a past paper solution for a user.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     already_unlocked: bool,
     *     coins_spent: int,
     *     coin_balance: int
     * }
     */
    public function unlockPastPaperSolution(User $user, PastPaper $pastPaper): array
    {
        if ($this->canAccessPastPaperSolution($user, $pastPaper)) {
            return [
                'success' => true,
                'message' => 'Past paper solution is already accessible.',
                'already_unlocked' => true,
                'coins_spent' => 0,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        }

        if ($user->coin_balance < $pastPaper->solution_coin_price) {
            throw ValidationException::withMessages([
                'coins' => "Insufficient coin balance. Unlocking this solution requires {$pastPaper->solution_coin_price} coins.",
            ]);
        }

        return DB::transaction(function () use ($user, $pastPaper): array {
            $this->coinService->spend(
                $user,
                $pastPaper->solution_coin_price,
                CoinTransactionType::SpentUnlock,
                $pastPaper
            );

            $unlock = UserPastPaperUnlock::firstOrNew([
                'user_id' => $user->id,
                'past_paper_id' => $pastPaper->id,
            ]);
            $unlock->solution_unlocked_at = now();
            $unlock->save();

            return [
                'success' => true,
                'message' => "Solution for '{$pastPaper->title}' unlocked successfully.",
                'already_unlocked' => false,
                'coins_spent' => $pastPaper->solution_coin_price,
                'coin_balance' => $this->coinService->getBalance($user),
            ];
        });
    }

    /**
     * Check if a user has an active subscription or free mode is enabled.
     */
    public function hasActiveSubscription(User $user): bool
    {
        if (AppSetting::isFreeModeEnabled()) {
            return true;
        }

        return $user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('end_date', '>=', now())
            ->exists();
    }

    /**
     * Get user's current subscription details and status.
     *
     * @return array{
     *     has_active_subscription: bool,
     *     free_mode_enabled: bool,
     *     subscription: array<string, mixed>|null
     * }
     */
    public function getSubscriptionStatus(User $user): array
    {
        $freeMode = AppSetting::isFreeModeEnabled();

        /** @var Subscription|null $activeSub */
        $activeSub = $user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('end_date', '>=', now())
            ->latest('end_date')
            ->first();

        $subData = null;
        if ($activeSub) {
            $subData = [
                'id' => $activeSub->id,
                'tier' => $activeSub->tier->value,
                'tier_label' => $activeSub->tier->label(),
                'status' => $activeSub->status->value,
                'coin_allotment' => $activeSub->coin_allotment,
                'amount_fcfa' => $activeSub->amount_fcfa,
                'start_date' => $activeSub->start_date->toIso8601String(),
                'end_date' => $activeSub->end_date->toIso8601String(),
            ];
        } else {
            /** @var Subscription|null $latestSub */
            $latestSub = $user->subscriptions()->latest('created_at')->first();
            if ($latestSub) {
                $subData = [
                    'id' => $latestSub->id,
                    'tier' => $latestSub->tier->value,
                    'tier_label' => $latestSub->tier->label(),
                    'status' => $latestSub->end_date->isPast() ? SubscriptionStatus::Expired->value : $latestSub->status->value,
                    'coin_allotment' => $latestSub->coin_allotment,
                    'amount_fcfa' => $latestSub->amount_fcfa,
                    'start_date' => $latestSub->start_date->toIso8601String(),
                    'end_date' => $latestSub->end_date->toIso8601String(),
                ];
            }
        }

        return [
            'has_active_subscription' => $this->hasActiveSubscription($user),
            'free_mode_enabled' => $freeMode,
            'subscription' => $subData,
        ];
    }
}
