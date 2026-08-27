<?php

namespace Database\Factories;

use App\Enums\MissionType;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mission>
 */
class MissionFactory extends Factory
{
    protected $model = Mission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'coin_reward' => fake()->numberBetween(5, 100),
            'type' => fake()->randomElement(MissionType::cases()),
            'is_active' => true,
        ];
    }
}
