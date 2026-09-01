<?php

namespace App\Enums;

enum AdRewardStatus: string
{
    case Pending = 'pending';
    case Redeemed = 'redeemed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Redeemed => 'Redeemed',
            self::Expired => 'Expired',
        };
    }
}
