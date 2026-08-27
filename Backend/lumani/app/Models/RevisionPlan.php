<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RevisionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $weekly_available_minutes
 * @property array<int, int> $available_days
 * @property CarbonInterface $generated_at
 * @property array<int, array{day: int, subject_id: int, subject_name: string, chapter_id: int|null, chapter_title: string|null, duration_minutes: int}> $plan_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class RevisionPlan extends Model
{
    /** @use HasFactory<RevisionPlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'weekly_available_minutes',
        'available_days',
        'generated_at',
        'plan_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'weekly_available_minutes' => 'integer',
            'available_days' => 'array',
            'generated_at' => 'datetime',
            'plan_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
