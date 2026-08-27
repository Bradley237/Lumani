<?php

namespace App\Models;

use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $title
 * @property int $order
 * @property int $coin_price
 * @property int|null $xp_reward
 * @property bool $is_free
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject $subject
 * @property-read Collection<int, Quiz> $quizzes
 * @property-read Collection<int, UserChapterUnlock> $unlocks
 * @property-read Collection<int, ChapterProgress> $progress
 * @property-read Collection<int, AiTutorConversation> $tutorConversations
 */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'title',
        'order',
        'coin_price',
        'xp_reward',
        'is_free',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'coin_price' => 'integer',
            'xp_reward' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    /**
     * Get the subject that owns the chapter.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the quizzes for the chapter.
     *
     * @return HasMany<Quiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Get the unlocks for the chapter.
     *
     * @return HasMany<UserChapterUnlock, $this>
     */
    public function unlocks(): HasMany
    {
        return $this->hasMany(UserChapterUnlock::class);
    }

    /**
     * Get the user progress records for the chapter.
     *
     * @return HasMany<ChapterProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ChapterProgress::class);
    }

    /**
     * Get the AI tutor conversations for the chapter.
     *
     * @return HasMany<AiTutorConversation, $this>
     */
    public function tutorConversations(): HasMany
    {
        return $this->hasMany(AiTutorConversation::class);
    }
}
