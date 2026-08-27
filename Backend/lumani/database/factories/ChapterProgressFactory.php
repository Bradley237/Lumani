<?php

namespace Database\Factories;

use App\Enums\ChapterProgressState;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterProgress>
 */
class ChapterProgressFactory extends Factory
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
            'state' => ChapterProgressState::InProgress,
            'last_accessed_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => ChapterProgressState::Completed,
            'completed_at' => now(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => ChapterProgressState::NotStarted,
            'last_accessed_at' => null,
            'completed_at' => null,
        ]);
    }
}
