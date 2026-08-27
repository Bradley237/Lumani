<?php

namespace App\Models;

use Database\Factories\ExamSessionAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exam_session_id
 * @property int $question_id
 * @property string|null $selected_choice
 * @property string|null $answer_text
 * @property int|null $points_awarded
 * @property int|null $suggested_points
 * @property string|null $suggested_justification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamSession $session
 * @property-read PastPaperQuestion $question
 */
class ExamSessionAnswer extends Model
{
    /** @use HasFactory<ExamSessionAnswerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'exam_session_id',
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
            'exam_session_id' => 'integer',
            'question_id' => 'integer',
            'points_awarded' => 'integer',
            'suggested_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * @return BelongsTo<PastPaperQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(PastPaperQuestion::class, 'question_id');
    }
}
