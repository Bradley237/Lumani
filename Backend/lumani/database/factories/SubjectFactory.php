<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Mathematics',
                'Physics',
                'Chemistry',
                'Biology',
                'Computer Science',
                'English Language',
                'French',
                'History',
                'Geography',
                'Economics',
            ]),
            'exam_subsystem' => fake()->randomElement(['anglophone', 'francophone', null]),
        ];
    }
}
