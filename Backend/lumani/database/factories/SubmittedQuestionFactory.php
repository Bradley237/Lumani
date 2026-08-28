<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Chapter;
use App\Models\SubmittedQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmittedQuestion>
 */
class SubmittedQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $choices = [
            'A' => fake()->sentence(4),
            'B' => fake()->sentence(4),
            'C' => fake()->sentence(4),
            'D' => fake()->sentence(4),
        ];
        $correct = fake()->randomElement(['A', 'B', 'C', 'D']);

        return [
            'submitted_by' => User::factory(),
            'chapter_id' => Chapter::factory(),
            'question_text' => fake()->sentence(8).'?',
            'answer_choices' => $choices,
            'correct_choice' => $correct,
            'explanation' => fake()->paragraph(),
            'review_status' => ReviewStatus::Pending,
            'reviewed_by' => null,
            'review_notes' => null,
        ];
    }

    /**
     * Indicate that the submission is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => ReviewStatus::Pending,
            'reviewed_by' => null,
            'review_notes' => null,
        ]);
    }

    /**
     * Indicate that the submission is approved.
     */
    public function approved(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => ReviewStatus::Approved,
            'reviewed_by' => $admin ? $admin->id : User::factory()->admin(),
            'review_notes' => null,
        ]);
    }

    /**
     * Indicate that the submission is rejected.
     */
    public function rejected(?User $admin = null, string $notes = 'Question does not meet syllabus standard.'): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => ReviewStatus::Rejected,
            'reviewed_by' => $admin ? $admin->id : User::factory()->admin(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Indicate that the submission is published.
     */
    public function published(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => ReviewStatus::Published,
            'reviewed_by' => $admin ? $admin->id : User::factory()->admin(),
        ]);
    }
}
