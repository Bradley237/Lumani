<?php

namespace Database\Factories;

use App\Models\AiTutorConversation;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiTutorConversation>
 */
class AiTutorConversationFactory extends Factory
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
            'chapter_id' => Chapter::factory(),
            'title' => fake()->sentence(3),
            'last_message_at' => now(),
        ];
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'chapter_id' => null,
            'title' => 'General Discussion',
        ]);
    }
}
