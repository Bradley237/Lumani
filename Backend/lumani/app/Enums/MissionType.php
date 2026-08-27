<?php

namespace App\Enums;

enum MissionType: string
{
    case DailyCheckin = 'daily_checkin';
    case WatchAd = 'watch_ad';
    case OneTime = 'one_time';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::DailyCheckin => 'Daily Check-in',
            self::WatchAd => 'Watch Ad',
            self::OneTime => 'One-Time',
            self::Referral => 'Referral',
        };
    }
}
