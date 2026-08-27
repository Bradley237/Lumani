<?php

namespace Database\Factories;

use App\Enums\ChallengeQuestionType;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyChallengeQuestion>
 */
class WeeklyChallengeQuestionFactory extends Factory
{
    protected $model = WeeklyChallengeQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekly_challenge_id' => WeeklyChallenge::factory(),
            'type' => ChallengeQuestionType::Mcq,
            'question_text' => fake()->sentence(8).'?',
            'options' => [
                'A' => fake()->sentence(3),
                'B' => fake()->sentence(3),
                'C' => fake()->sentence(3),
                'D' => fake()->sentence(3),
            ],
            'correct_choice' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'marking_scheme' => null,
            'max_points' => 10,
            'order' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the question is structural.
     */
    public function structural(?string $markingScheme = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChallengeQuestionType::Structural,
            'options' => null,
            'correct_choice' => null,
            'marking_scheme' => $markingScheme ?? 'Model answer: expected key derivation steps and formulas.',
            'max_points' => 20,
        ]);
    }
}
