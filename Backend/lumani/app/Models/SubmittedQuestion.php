<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Mail\AdminNewSubmissionEmail;
use Database\Factories\SubmittedQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * @property int $id
 * @property int|null $submitted_by
 * @property int $chapter_id
 * @property string $question_text
 * @property array<string, mixed>|list<mixed> $answer_choices
 * @property string $correct_choice
 * @property string|null $explanation
 * @property ReviewStatus $review_status
 * @property int|null $reviewed_by
 * @property string|null $review_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $submitter
 * @property-read Chapter $chapter
 * @property-read User|null $reviewer
 */
class SubmittedQuestion extends Model
{
    /** @use HasFactory<SubmittedQuestionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'submitted_by',
        'chapter_id',
        'question_text',
        'answer_choices',
        'correct_choice',
        'explanation',
        'review_status',
        'reviewed_by',
        'review_notes',
    ];

    /**
     * Boot the model and dispatch email notifications on creation.
     */
    protected static function booted(): void
    {
        static::created(function (SubmittedQuestion $submittedQuestion): void {
            $admins = User::where('role', UserRole::Admin)->get();
            foreach ($admins as $admin) {
                if (! empty($admin->email)) {
                    Mail::to($admin->email)->send(
                        new AdminNewSubmissionEmail($submittedQuestion, $admin)
                    );
                }
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_choices' => 'array',
            'review_status' => ReviewStatus::class,
        ];
    }

    /**
     * Get the user who submitted the question.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the chapter that this question belongs to.
     *
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the admin who reviewed the question.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
