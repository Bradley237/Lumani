<?php

namespace App\Models;

use App\Enums\AdRewardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property AdRewardStatus $status
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class AdRewardRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'status',
        'redeemed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdRewardStatus::class,
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === AdRewardStatus::Pending;
    }

    public function isRedeemed(): bool
    {
        return $this->status === AdRewardStatus::Redeemed;
    }

    public function isExpired(): bool
    {
        return $this->status === AdRewardStatus::Expired;
    }

    public function isOlderThanMinutes(int $minutes = 10): bool
    {
        if (! $this->created_at) {
            return false;
        }

        return $this->created_at->lt(now()->subMinutes($minutes));
    }
}
