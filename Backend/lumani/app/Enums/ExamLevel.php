<?php

namespace App\Enums;

enum ExamLevel: string
{
    case OrdinaryLevel = 'ordinary_level';
    case AdvancedLevel = 'advanced_level';
    case Bepc = 'bepc';
    case Probatoire = 'probatoire';
    case Bac = 'bac';

    public function label(): string
    {
        return match ($this) {
            self::OrdinaryLevel => 'Ordinary Level (O-Level)',
            self::AdvancedLevel => 'Advanced Level (A-Level)',
            self::Bepc => 'BEPC',
            self::Probatoire => 'Probatoire',
            self::Bac => 'Baccalauréat (BAC)',
        };
    }

    public function subsystem(): ExamSubsystem
    {
        return match ($this) {
            self::OrdinaryLevel, self::AdvancedLevel => ExamSubsystem::Gce,
            self::Bepc, self::Probatoire, self::Bac => ExamSubsystem::Obc,
        };
    }

    /**
     * Get the valid exam levels for a given subsystem.
     *
     * @return list<self>
     */
    public static function forSubsystem(ExamSubsystem|string $subsystem): array
    {
        if (is_string($subsystem)) {
            $subsystem = ExamSubsystem::tryFrom($subsystem);
        }

        if (! $subsystem) {
            return [];
        }

        return $subsystem->validLevels();
    }
}
