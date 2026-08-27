<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'title' => fake()->sentence(3),
            'order' => fake()->numberBetween(1, 10),
            'coin_price' => 50,
            'xp_reward' => fake()->numberBetween(50, 200),
            'is_free' => false,
        ];
    }

    /**
     * Indicate that the chapter is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }
}
