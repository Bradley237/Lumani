<?php

namespace App\Models;

use App\Enums\MissionType;
use Database\Factories\MissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string|null $description
 * @property int $coin_reward
 * @property MissionType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'title',
        'description',
        'coin_reward',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coin_reward' => 'integer',
            'type' => MissionType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UserMissionProgress, $this>
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserMissionProgress::class);
    }
}
