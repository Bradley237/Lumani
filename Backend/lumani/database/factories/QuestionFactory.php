<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
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
            'quiz_id' => Quiz::factory(),
            'question_text' => fake()->sentence(8).'?',
            'answer_choices' => $choices,
            'correct_choice' => $correct,
            'explanation' => fake()->paragraph(),
        ];
    }
}
