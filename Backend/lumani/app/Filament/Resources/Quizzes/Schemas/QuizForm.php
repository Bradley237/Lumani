<?php

namespace App\Filament\Resources\Quizzes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuizForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chapter_id')
                    ->relationship('chapter', 'title')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->title} (".($record->subject->name ?? 'No Subject').')')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('passing_score')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(70)
                    ->helperText('Minimum score percentage required to pass this quiz'),
            ]);
    }
}
