<?php

namespace App\Models;

use App\Enums\ChallengeQuestionType;
use Database\Factories\WeeklyChallengeQuestionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $weekly_challenge_id
 * @property ChallengeQuestionType $type
 * @property string $question_text
 * @property array<string, string>|null $options
 * @property string|null $correct_choice
 * @property string|null $marking_scheme
 * @property int $max_points
 * @property int $order
 * @property string|null $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WeeklyChallenge $challenge
 * @property-read Collection<int, UserChallengeAnswer> $answers
 */
class WeeklyChallengeQuestion extends Model
{
    /** @use HasFactory<WeeklyChallengeQuestionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'weekly_challenge_id',
        'type',
        'question_text',
        'options',
        'correct_choice',
        'marking_scheme',
        'max_points',
        'order',
        'image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_challenge_id' => 'integer',
            'type' => ChallengeQuestionType::class,
            'options' => 'array',
            'max_points' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WeeklyChallenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(WeeklyChallenge::class, 'weekly_challenge_id');
    }

    /**
     * @return HasMany<UserChallengeAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(UserChallengeAnswer::class, 'question_id');
    }
}
