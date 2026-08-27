<?php

namespace App\Models;

use App\Enums\ChallengeStatus;
use Carbon\CarbonInterface;
use Database\Factories\WeeklyChallengeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property string|null $exam_subsystem
 * @property string|null $level
 * @property string $title
 * @property int $time_limit_minutes
 * @property CarbonInterface $week_start_date
 * @property CarbonInterface $week_end_date
 * @property ChallengeStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject $subject
 * @property-read User|null $creator
 * @property-read Collection<int, WeeklyChallengeQuestion> $questions
 * @property-read Collection<int, UserChallengeAttempt> $attempts
 */
class WeeklyChallenge extends Model
{
    /** @use HasFactory<WeeklyChallengeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'exam_subsystem',
        'level',
        'title',
        'time_limit_minutes',
        'week_start_date',
        'week_end_date',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'time_limit_minutes' => 'integer',
            'week_start_date' => 'datetime',
            'week_end_date' => 'datetime',
            'status' => ChallengeStatus::class,
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WeeklyChallengeQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(WeeklyChallengeQuestion::class)->orderBy('order', 'asc');
    }

    /**
     * @return HasMany<UserChallengeAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(UserChallengeAttempt::class);
    }

    /**
     * Check if the challenge is currently within its active week window.
     */
    public function isWithinWeekWindow(): bool
    {
        $now = now();

        return $now->gte($this->week_start_date) && $now->lte($this->week_end_date);
    }
}
