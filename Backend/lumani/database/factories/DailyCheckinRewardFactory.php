<?php

namespace Database\Factories;

use App\Models\DailyCheckinReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyCheckinReward>
 */
class DailyCheckinRewardFactory extends Factory
{
    protected $model = DailyCheckinReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day' => fake()->unique()->numberBetween(1, 7),
            'coin_reward' => fake()->numberBetween(3, 20),
        ];
    }
}
