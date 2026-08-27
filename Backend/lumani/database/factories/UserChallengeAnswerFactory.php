<?php

namespace Database\Factories;

use App\Models\UserChallengeAnswer;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallengeQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChallengeAnswer>
 */
class UserChallengeAnswerFactory extends Factory
{
    protected $model = UserChallengeAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => UserChallengeAttempt::factory(),
            'question_id' => WeeklyChallengeQuestion::factory(),
            'selected_choice' => 'A',
            'answer_text' => null,
            'points_awarded' => 10,
            'suggested_points' => null,
            'suggested_justification' => null,
        ];
    }
}
