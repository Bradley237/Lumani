<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement(SubscriptionTier::cases());

        return [
            'user_id' => User::factory(),
            'tier' => $tier,
            'coin_allotment' => $tier->coinAllotment(),
            'amount_fcfa' => $tier->priceFcfa(),
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => SubscriptionStatus::Active,
        ];
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth(),
            'status' => SubscriptionStatus::Expired,
        ]);
    }
}
