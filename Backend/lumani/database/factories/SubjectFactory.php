<?php

namespace Database\Factories;

use App\Enums\ExamSubsystem;
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
        /** @var ExamSubsystem|null $subsystem */
        $subsystem = fake()->randomElement([ExamSubsystem::Gce, ExamSubsystem::Obc, null]);
        $level = $subsystem ? fake()->randomElement([...$subsystem->validLevels(), null]) : null;

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
            'exam_subsystem' => $subsystem,
            'level' => $level,
        ];
    }
}
