<?php

namespace App\Models;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ExamSubsystem|null $exam_subsystem
 * @property ExamLevel|null $level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'exam_subsystem',
        'level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_subsystem' => ExamSubsystem::class,
            'level' => ExamLevel::class,
        ];
    }

    /**
     * Get the chapters for the subject.
     *
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('order');
    }

    /**
     * Get all chapter unlocks for the subject.
     *
     * @return HasManyThrough<UserChapterUnlock, Chapter, $this>
     */
    public function chapterUnlocks(): HasManyThrough
    {
        return $this->hasManyThrough(UserChapterUnlock::class, Chapter::class);
    }
}
