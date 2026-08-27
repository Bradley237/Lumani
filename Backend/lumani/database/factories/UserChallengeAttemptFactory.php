<?php

namespace Database\Factories;

use App\Enums\ChallengeAttemptStatus;
use App\Models\User;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChallengeAttempt>
 */
class UserChallengeAttemptFactory extends Factory
{
    protected $model = UserChallengeAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekly_challenge_id' => WeeklyChallenge::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'status' => ChallengeAttemptStatus::InProgress,
            'total_score_percent' => null,
            'reward_coins_awarded' => null,
        ];
    }

    /**
     * Indicate that the attempt is submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
            'status' => ChallengeAttemptStatus::Submitted,
        ]);
    }

    /**
     * Indicate that the attempt is graded.
     */
    public function graded(float $scorePercent = 85.0, int $coins = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
            'status' => ChallengeAttemptStatus::Graded,
            'total_score_percent' => $scorePercent,
            'reward_coins_awarded' => $coins,
        ]);
    }
}
