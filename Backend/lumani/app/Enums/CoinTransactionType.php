<?php

namespace App\Enums;

enum CoinTransactionType: string
{
    case EarnedMission = 'earned_mission';
    case EarnedReferral = 'earned_referral';
    case EarnedXpConversion = 'earned_xp_conversion';
    case EarnedChallenge = 'earned_challenge';
    case SpentUnlock = 'spent_unlock';
    case SpentAiTutor = 'spent_ai_tutor';

    public function label(): string
    {
        return match ($this) {
            self::EarnedMission => 'Earned from Mission',
            self::EarnedReferral => 'Earned from Referral',
            self::EarnedXpConversion => 'Earned from XP Conversion',
            self::EarnedChallenge => 'Earned from Weekly Challenge',
            self::SpentUnlock => 'Spent on Unlock',
            self::SpentAiTutor => 'Spent on AI Tutor',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [
            self::EarnedMission,
            self::EarnedReferral,
            self::EarnedXpConversion,
            self::EarnedChallenge,
        ], true);
    }
}
