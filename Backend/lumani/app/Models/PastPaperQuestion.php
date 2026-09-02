<?php

namespace App\Models;

use App\Enums\PastPaperQuestionType;
use Database\Factories\PastPaperQuestionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $past_paper_id
 * @property PastPaperQuestionType $type
 * @property string $question_text
 * @property array<string, string>|null $options
 * @property string|null $correct_choice
 * @property string|null $marking_scheme
 * @property int $max_points
 * @property int $order
 * @property string|null $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PastPaper $pastPaper
 * @property-read Collection<int, ExamSessionAnswer> $answers
 */
class PastPaperQuestion extends Model
{
    /** @use HasFactory<PastPaperQuestionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'past_paper_id',
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
            'past_paper_id' => 'integer',
            'type' => PastPaperQuestionType::class,
            'options' => 'array',
            'max_points' => 'integer',
            'order' => 'integer',
        ];
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
        return $this->hasMany(ExamSessionAnswer::class, 'question_id');
    }
}
