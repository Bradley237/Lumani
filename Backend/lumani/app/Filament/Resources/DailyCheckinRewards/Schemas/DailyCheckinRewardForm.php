<?php

namespace App\Filament\Resources\DailyCheckinRewards\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DailyCheckinRewardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('day')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(7)
                    ->unique(ignoreRecord: true)
                    ->helperText('Day number (1 to 7)'),
                TextInput::make('coin_reward')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Coin reward for this day'),
            ]);
    }
}
