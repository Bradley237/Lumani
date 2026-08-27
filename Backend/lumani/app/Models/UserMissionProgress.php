<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserMissionProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $mission_id
 * @property int|null $current_streak_day
 * @property CarbonInterface|null $last_completed_at
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Mission $mission
 */
class UserMissionProgress extends Model
{
    /** @use HasFactory<UserMissionProgressFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'user_mission_progress';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'mission_id',
        'current_streak_day',
        'last_completed_at',
        'completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_streak_day' => 'integer',
            'last_completed_at' => 'datetime',
            'completed' => 'boolean',
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
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
