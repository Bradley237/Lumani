<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\User;
use App\Models\UserMissionProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMissionProgress>
 */
class UserMissionProgressFactory extends Factory
{
    protected $model = UserMissionProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mission_id' => Mission::factory(),
            'current_streak_day' => 1,
            'last_completed_at' => now(),
            'completed' => false,
        ];
    }
}
