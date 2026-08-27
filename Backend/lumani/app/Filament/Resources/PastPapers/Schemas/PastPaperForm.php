<?php

namespace App\Filament\Resources\PastPapers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PastPaperForm
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
                Select::make('exam_subsystem')
                    ->options([
                        'anglophone' => 'Anglophone Subsystem',
                        'francophone' => 'Francophone Subsystem',
                        'general' => 'General / Both',
                    ])
                    ->placeholder('Select exam subsystem (optional)'),
                Select::make('level')
                    ->options([
                        'O-Level' => 'O-Level (Ordinary Level)',
                        'A-Level' => 'A-Level (Advanced Level)',
                        'BEPC' => 'BEPC',
                        'Probatoire' => 'Probatoire',
                        'Baccalaureat' => 'Baccalauréat',
                    ])
                    ->placeholder('Select academic level (optional)'),
                TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->minValue(1990)
                    ->maxValue(2100)
                    ->default(date('Y')),
                TextInput::make('coin_price')
                    ->required()
                    ->numeric()
                    ->default(15)
                    ->minValue(0)
                    ->helperText('Coin cost to unlock questions paper'),
                TextInput::make('file_path')
                    ->label('Paper File Path / URL')
                    ->maxLength(255)
                    ->helperText('Storage path or direct URL to questions PDF'),
                TextInput::make('solution_coin_price')
                    ->required()
                    ->numeric()
                    ->default(20)
                    ->minValue(0)
                    ->helperText('Coin cost to unlock solutions document'),
                TextInput::make('solution_file_path')
                    ->label('Solution File Path / URL')
                    ->maxLength(255)
                    ->helperText('Storage path or direct URL to solution PDF'),
            ]);
    }
}
