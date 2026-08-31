<?php

namespace App\Models;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use Database\Factories\PastPaperFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property ExamSubsystem|null $exam_subsystem
 * @property ExamLevel|null $level
 * @property int $year
 * @property string $title
 * @property string|null $file_path
 * @property int $coin_price
 * @property string|null $solution_file_path
 * @property int $solution_coin_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject $subject
 * @property-read Collection<int, UserPastPaperUnlock> $unlocks
 * @property-read Collection<int, PastPaperQuestion> $questions
 * @property-read Collection<int, ExamSession> $examSessions
 */
class PastPaper extends Model
{
    /** @use HasFactory<PastPaperFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'exam_subsystem',
        'level',
        'year',
        'title',
        'file_path',
        'coin_price',
        'solution_file_path',
        'solution_coin_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_subsystem' => ExamSubsystem::class,
            'level' => ExamLevel::class,
            'subject_id' => 'integer',
            'year' => 'integer',
            'coin_price' => 'integer',
            'solution_coin_price' => 'integer',
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
     * @return HasMany<UserPastPaperUnlock, $this>
     */
    public function unlocks(): HasMany
    {
        return $this->hasMany(UserPastPaperUnlock::class);
    }

    /**
     * @return HasMany<PastPaperQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PastPaperQuestion::class);
    }

    /**
     * @return HasMany<ExamSession, $this>
     */
    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }
}
