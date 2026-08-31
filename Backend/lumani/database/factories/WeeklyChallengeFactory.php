<?php

namespace Database\Factories;

use App\Enums\ChallengeStatus;
use App\Enums\ExamSubsystem;
use App\Models\Subject;
use App\Models\User;
use App\Models\WeeklyChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyChallenge>
 */
class WeeklyChallengeFactory extends Factory
{
    protected $model = WeeklyChallenge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var ExamSubsystem|null $subsystem */
        $subsystem = fake()->randomElement([ExamSubsystem::Gce, ExamSubsystem::Obc, null]);
        $level = $subsystem ? fake()->randomElement($subsystem->validLevels()) : null;

        return [
            'subject_id' => Subject::factory(),
            'exam_subsystem' => $subsystem,
            'level' => $level,
            'title' => 'Weekly Challenge: '.fake()->sentence(3),
            'time_limit_minutes' => 30,
            'week_start_date' => now()->startOfWeek(),
            'week_end_date' => now()->endOfWeek(),
            'status' => ChallengeStatus::Published,
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * Indicate that the challenge is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeStatus::Draft,
        ]);
    }

    /**
     * Indicate that the challenge is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeStatus::Closed,
        ]);
    }
}
