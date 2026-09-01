<?php

namespace App\Enums;

enum BusinessSettingType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Integer => 'Integer',
            self::Decimal => 'Decimal',
            self::Boolean => 'Boolean',
        };
    }
}
