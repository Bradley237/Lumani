<?php

namespace App\Models;

use Database\Factories\UserChallengeAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $attempt_id
 * @property int $question_id
 * @property string|null $selected_choice
 * @property string|null $answer_text
 * @property int|null $points_awarded
 * @property int|null $suggested_points
 * @property string|null $suggested_justification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserChallengeAttempt $attempt
 * @property-read WeeklyChallengeQuestion $question
 */
class UserChallengeAnswer extends Model
{
    /** @use HasFactory<UserChallengeAnswerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_choice',
        'answer_text',
        'points_awarded',
        'suggested_points',
        'suggested_justification',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_id' => 'integer',
            'question_id' => 'integer',
            'points_awarded' => 'integer',
            'suggested_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<UserChallengeAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(UserChallengeAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<WeeklyChallengeQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(WeeklyChallengeQuestion::class, 'question_id');
    }
}
