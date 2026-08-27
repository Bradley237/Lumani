<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\PastPaperQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSessionAnswer>
 */
class ExamSessionAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'question_id' => PastPaperQuestion::factory(),
            'selected_choice' => 'A',
            'answer_text' => null,
            'points_awarded' => null,
            'suggested_points' => null,
            'suggested_justification' => null,
        ];
    }

    public function structural(?int $suggestedPoints = 15, ?string $justification = 'Clear explanation with correct formula'): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_choice' => null,
            'answer_text' => 'Sample student essay answer providing full explanation.',
            'suggested_points' => $suggestedPoints,
            'suggested_justification' => $justification,
        ]);
    }
}
