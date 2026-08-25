<?php

namespace App\Filament\Resources\Questions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('quiz_id')
                    ->relationship('quiz', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Quiz #{$record->id} (Chapter: ".($record->chapter?->title ?? 'N/A').')')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('question_text')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                KeyValue::make('answer_choices')
                    ->keyLabel('Choice Key (e.g. A, B, C, D)')
                    ->valueLabel('Choice Text')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('correct_choice')
                    ->required()
                    ->helperText('The key of the correct option (e.g., A, B, C, or D)'),
                Textarea::make('explanation')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
