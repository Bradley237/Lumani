<?php

namespace App\Models;

use App\Enums\ExamSessionStatus;
use Carbon\CarbonInterface;
use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $past_paper_id
 * @property int $max_allowed_minutes
 * @property int $selected_minutes
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $submitted_at
 * @property ExamSessionStatus $status
 * @property float|null $total_score_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read PastPaper $pastPaper
 * @property-read Collection<int, ExamSessionAnswer> $answers
 */
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'past_paper_id',
        'max_allowed_minutes',
        'selected_minutes',
        'started_at',
        'submitted_at',
        'status',
        'total_score_percent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'past_paper_id' => 'integer',
            'max_allowed_minutes' => 'integer',
            'selected_minutes' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => ExamSessionStatus::class,
            'total_score_percent' => 'float',
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
     * @return BelongsTo<PastPaper, $this>
     */
    public function pastPaper(): BelongsTo
    {
        return $this->belongsTo(PastPaper::class);
    }

    /**
     * @return HasMany<ExamSessionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamSessionAnswer::class);
    }
}
