<?php

namespace App\Filament\Resources\Chapters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChapterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Display order in the subject curriculum'),
                TextInput::make('coin_price')
                    ->required()
                    ->numeric()
                    ->default(50)
                    ->minValue(0)
                    ->helperText('Price in coins to permanently unlock this chapter'),
                TextInput::make('xp_reward')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Experience points (XP) rewarded when chapter is completed'),
                Toggle::make('is_free')
                    ->default(false)
                    ->helperText('Mark this chapter as free for all students'),
            ]);
    }
}
