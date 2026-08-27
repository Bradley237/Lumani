<?php

namespace Database\Factories;

use App\Enums\PastPaperQuestionType;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PastPaperQuestion>
 */
class PastPaperQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'past_paper_id' => PastPaper::factory(),
            'type' => PastPaperQuestionType::Mcq,
            'question_text' => fake()->sentence(8).'?',
            'options' => [
                'A' => fake()->sentence(3),
                'B' => fake()->sentence(3),
                'C' => fake()->sentence(3),
                'D' => fake()->sentence(3),
            ],
            'correct_choice' => 'A',
            'marking_scheme' => null,
            'max_points' => 10,
            'order' => fake()->numberBetween(0, 10),
        ];
    }

    public function structural(?string $markingScheme = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PastPaperQuestionType::Structural,
            'options' => null,
            'correct_choice' => null,
            'marking_scheme' => $markingScheme ?? 'Award full marks for clear definition and accurate mathematical derivation.',
            'max_points' => 20,
        ]);
    }
}
