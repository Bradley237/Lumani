<?php

namespace App\Filament\Resources\Missions\Schemas;

use App\Enums\MissionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('coin_reward')
                    ->numeric()
                    ->required()
                    ->default(10)
                    ->minValue(0),
                Select::make('type')
                    ->options(collect(MissionType::cases())->mapWithKeys(
                        fn (MissionType $type) => [$type->value => $type->label()]
                    )->all())
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
