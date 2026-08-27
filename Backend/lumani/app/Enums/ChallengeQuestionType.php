<?php

namespace App\Enums;

enum ChallengeQuestionType: string
{
    case Mcq = 'mcq';
    case Structural = 'structural';

    public function label(): string
    {
        return match ($this) {
            self::Mcq => 'Multiple Choice (MCQ)',
            self::Structural => 'Structural / Essay',
        };
    }
}
