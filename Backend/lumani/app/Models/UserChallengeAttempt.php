<?php

namespace App\Models;

use App\Enums\ChallengeAttemptStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserChallengeAttemptFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $weekly_challenge_id
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $submitted_at
 * @property ChallengeAttemptStatus $status
 * @property float|null $total_score_percent
 * @property int|null $reward_coins_awarded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read WeeklyChallenge $challenge
 * @property-read Collection<int, UserChallengeAnswer> $answers
 */
class UserChallengeAttempt extends Model
{
    /** @use HasFactory<UserChallengeAttemptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'weekly_challenge_id',
        'started_at',
        'submitted_at',
        'status',
        'total_score_percent',
        'reward_coins_awarded',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'weekly_challenge_id' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => ChallengeAttemptStatus::class,
            'total_score_percent' => 'float',
            'reward_coins_awarded' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->hasMany(UserChallengeAnswer::class, 'attempt_id');
    }
}
