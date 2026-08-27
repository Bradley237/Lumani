<?php

namespace App\Enums;

enum ExamSessionStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted (Awaiting Grading)',
            self::Graded => 'Graded',
        };
    }
}
