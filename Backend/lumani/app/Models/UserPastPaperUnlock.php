<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserPastPaperUnlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $past_paper_id
 * @property CarbonInterface|null $paper_unlocked_at
 * @property CarbonInterface|null $solution_unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read PastPaper $pastPaper
 */
class UserPastPaperUnlock extends Model
{
    /** @use HasFactory<UserPastPaperUnlockFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'user_past_paper_unlocks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'past_paper_id',
        'paper_unlocked_at',
        'solution_unlocked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'past_paper_id' => 'integer',
            'paper_unlocked_at' => 'datetime',
            'solution_unlocked_at' => 'datetime',
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
}
