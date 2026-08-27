<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CareerPathwayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $generated_at
 * @property array<int, array{career_profile_id: int, match_score: int, reasoning: string}> $recommendations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class CareerPathway extends Model
{
    /** @use HasFactory<CareerPathwayFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'generated_at',
        'recommendations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'generated_at' => 'datetime',
            'recommendations' => 'array',
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
