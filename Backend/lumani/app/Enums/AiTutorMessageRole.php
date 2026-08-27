<?php

namespace App\Enums;

enum AiTutorMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Assistant => 'Lumani AI Tutor',
        };
    }
}
