<?php

namespace App\Enums;

enum ChallengeAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted (Pending Grading)',
            self::Graded => 'Graded',
        };
    }
}
