<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Grant or extend a user's subscription and award the corresponding coin allotment.
     */
    public function grantSubscription(User $user, SubscriptionTier $tier, int $durationMonths = 1): Subscription
    {
        return DB::transaction(function () use ($user, $tier, $durationMonths): Subscription {
            /** @var Subscription|null $activeSub */
            $activeSub = $user->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('end_date', '>', now())
                ->latest('end_date')
                ->first();

            $startDate = $activeSub ? $activeSub->end_date : now();
            $endDate = (clone $startDate)->addMonths($durationMonths);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'tier' => $tier,
                'coin_allotment' => $tier->coinAllotment(),
                'amount_fcfa' => $tier->priceFcfa(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => SubscriptionStatus::Active,
            ]);

            // Credit the tier's coin allotment to user's balance
            $this->coinService->award(
                $user,
                $tier->coinAllotment(),
                CoinTransactionType::EarnedSubscription,
                $subscription
            );

            return $subscription;
        });
    }
}
