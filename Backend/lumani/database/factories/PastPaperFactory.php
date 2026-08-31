<?php

namespace Database\Factories;

use App\Enums\ExamSubsystem;
use App\Models\PastPaper;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PastPaper>
 */
class PastPaperFactory extends Factory
{
    protected $model = PastPaper::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var ExamSubsystem|null $subsystem */
        $subsystem = fake()->randomElement([ExamSubsystem::Gce, ExamSubsystem::Obc, null]);
        $level = $subsystem ? fake()->randomElement($subsystem->validLevels()) : null;

        return [
            'subject_id' => Subject::factory(),
            'exam_subsystem' => $subsystem,
            'level' => $level,
            'year' => fake()->numberBetween(2018, 2025),
            'title' => fake()->sentence(3).' Past Paper',
            'file_path' => 'papers/'.fake()->uuid().'.pdf',
            'coin_price' => 15,
            'solution_file_path' => 'solutions/'.fake()->uuid().'.pdf',
            'solution_coin_price' => 20,
        ];
    }
}
