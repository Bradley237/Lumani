<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserChapterUnlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $chapter_id
 * @property CarbonInterface $unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Chapter $chapter
 */
class UserChapterUnlock extends Model
{
    /** @use HasFactory<UserChapterUnlockFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'user_chapter_unlocks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'chapter_id',
        'unlocked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'chapter_id' => 'integer',
            'unlocked_at' => 'datetime',
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
