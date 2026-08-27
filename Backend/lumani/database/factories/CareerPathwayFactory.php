<?php

namespace Database\Factories;

use App\Models\CareerPathway;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareerPathway>
 */
class CareerPathwayFactory extends Factory
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
            'generated_at' => now(),
            'recommendations' => [
                [
                    'career_profile_id' => 1,
                    'match_score' => 92,
                    'reasoning' => 'Strong performance in Mathematics and Physics indicates high aptitude for engineering.',
                ],
            ],
        ];
    }
}
