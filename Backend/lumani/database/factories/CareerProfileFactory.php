<?php

namespace Database\Factories;

use App\Enums\JobDemand;
use App\Models\CareerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareerProfile>
 */
class CareerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraph(3),
            'average_salary' => '600,000 - 1,500,000 FCFA/mo',
            'job_demand' => fake()->randomElement(JobDemand::cases()),
            'related_subjects' => ['Mathematics', 'Physics', 'Computer Science'],
        ];
    }
}
