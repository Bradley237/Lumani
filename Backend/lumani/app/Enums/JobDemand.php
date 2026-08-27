<?php

namespace App\Enums;

enum JobDemand: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case VeryHigh = 'very_high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low Demand',
            self::Moderate => 'Moderate Demand',
            self::High => 'High Demand',
            self::VeryHigh => 'Very High Demand',
        };
    }
}
