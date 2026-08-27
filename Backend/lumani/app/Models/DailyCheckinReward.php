<?php

namespace App\Models;

use Database\Factories\DailyCheckinRewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $day
 * @property int $coin_reward
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyCheckinReward extends Model
{
    /** @use HasFactory<DailyCheckinRewardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day',
        'coin_reward',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'coin_reward' => 'integer',
        ];
    }
}
