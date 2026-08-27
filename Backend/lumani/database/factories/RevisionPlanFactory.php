<?php

namespace Database\Factories;

use App\Models\RevisionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevisionPlan>
 */
class RevisionPlanFactory extends Factory
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
            'weekly_available_minutes' => 300,
            'available_days' => [1, 3, 5],
            'generated_at' => now(),
            'plan_data' => [
                [
                    'day' => 1,
                    'subject_id' => 1,
                    'subject_name' => 'Mathematics',
                    'chapter_id' => 1,
                    'chapter_title' => 'Algebra Foundations',
                    'duration_minutes' => 100,
                ],
                [
                    'day' => 3,
                    'subject_id' => 2,
                    'subject_name' => 'Physics',
                    'chapter_id' => 2,
                    'chapter_title' => 'Kinematics',
                    'duration_minutes' => 100,
                ],
                [
                    'day' => 5,
                    'subject_id' => 3,
                    'subject_name' => 'Chemistry',
                    'chapter_id' => 3,
                    'chapter_title' => 'Atomic Structure',
                    'duration_minutes' => 100,
                ],
            ],
        ];
    }
}
