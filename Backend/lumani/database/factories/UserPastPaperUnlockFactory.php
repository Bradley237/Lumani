<?php

namespace Database\Factories;

use App\Models\PastPaper;
use App\Models\User;
use App\Models\UserPastPaperUnlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPastPaperUnlock>
 */
class UserPastPaperUnlockFactory extends Factory
{
    protected $model = UserPastPaperUnlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'past_paper_id' => PastPaper::factory(),
            'paper_unlocked_at' => now(),
            'solution_unlocked_at' => null,
        ];
    }
}
