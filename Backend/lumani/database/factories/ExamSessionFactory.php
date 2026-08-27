<?php

namespace Database\Factories;

use App\Enums\ExamSessionStatus;
use App\Models\ExamSession;
use App\Models\PastPaper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'past_paper_id' => PastPaper::factory(),
            'max_allowed_minutes' => 90,
            'selected_minutes' => 90,
            'started_at' => now(),
            'submitted_at' => null,
            'status' => ExamSessionStatus::InProgress,
            'total_score_percent' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamSessionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function graded(float $scorePercent = 85.0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamSessionStatus::Graded,
            'submitted_at' => now()->subMinutes(10),
            'total_score_percent' => $scorePercent,
        ]);
    }
}
