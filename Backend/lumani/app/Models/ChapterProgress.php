<?php

namespace App\Models;

use App\Enums\ChapterProgressState;
use Carbon\CarbonInterface;
use Database\Factories\ChapterProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $chapter_id
 * @property ChapterProgressState $state
 * @property CarbonInterface|null $last_accessed_at
 * @property CarbonInterface|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Chapter $chapter
 */
class ChapterProgress extends Model
{
    /** @use HasFactory<ChapterProgressFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'chapter_progress';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'chapter_id',
        'state',
        'last_accessed_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'chapter_id' => 'integer',
            'state' => ChapterProgressState::class,
            'last_accessed_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
