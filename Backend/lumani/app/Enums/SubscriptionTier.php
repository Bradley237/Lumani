<?php

namespace App\Enums;

enum SubscriptionTier: string
{
    case Tier2000 = 'tier_2000';
    case Tier5000 = 'tier_5000';

    public function label(): string
    {
        return match ($this) {
            self::Tier2000 => 'Standard Plan (2,000 FCFA)',
            self::Tier5000 => 'Premium Plan (5,000 FCFA)',
        };
    }

    public function coinAllotment(): int
    {
        return match ($this) {
            self::Tier2000 => 500,
            self::Tier5000 => 1500,
        };
    }

    public function priceFcfa(): int
    {
        return match ($this) {
            self::Tier2000 => 2000,
            self::Tier5000 => 5000,
        };
    }
}
