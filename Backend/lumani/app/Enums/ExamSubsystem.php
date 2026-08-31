<?php

namespace App\Enums;

enum ExamSubsystem: string
{
    case Gce = 'gce';
    case Obc = 'obc';

    public function label(): string
    {
        return match ($this) {
            self::Gce => 'GCE (Anglophone Subsystem)',
            self::Obc => 'OBC (Francophone Subsystem)',
        };
    }

    /**
     * Get valid academic levels for this exam subsystem.
     *
     * @return list<ExamLevel>
     */
    public function validLevels(): array
    {
        return match ($this) {
            self::Gce => [
                ExamLevel::OrdinaryLevel,
                ExamLevel::AdvancedLevel,
            ],
            self::Obc => [
                ExamLevel::Bepc,
                ExamLevel::Probatoire,
                ExamLevel::Bac,
            ],
        };
    }

    /**
     * Get full mapping of subsystems to their allowed level strings.
     *
     * @return array<string, list<string>>
     */
    public static function mapping(): array
    {
        return [
            self::Gce->value => [
                ExamLevel::OrdinaryLevel->value,
                ExamLevel::AdvancedLevel->value,
            ],
            self::Obc->value => [
                ExamLevel::Bepc->value,
                ExamLevel::Probatoire->value,
                ExamLevel::Bac->value,
            ],
        ];
    }
}
