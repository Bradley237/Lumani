<?php

namespace Database\Factories;

use App\Enums\AiTutorMessageRole;
use App\Models\AiTutorConversation;
use App\Models\AiTutorMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiTutorMessage>
 */
class AiTutorMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => AiTutorConversation::factory(),
            'role' => AiTutorMessageRole::User,
            'content' => fake()->sentence(8),
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AiTutorMessageRole::Assistant,
            'content' => fake()->paragraph(2),
        ]);
    }
}
