<?php

namespace App\Filament\Resources\PastPapers\Schemas;

use App\Enums\PastPaperQuestionType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PastPaperForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Past Paper Details')
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
                    ]),
                Section::make('Exam-Condition Questions')
                    ->description('Add Multiple Choice or Structural/Essay questions for timed exam practice mode.')
                    ->components([
                        Repeater::make('questions')
                            ->relationship('questions')
                            ->defaultItems(0)
                            ->addActionLabel('Add Question')
                            ->schema([
                                Select::make('type')
                                    ->options(collect(PastPaperQuestionType::cases())->mapWithKeys(
                                        fn (PastPaperQuestionType $type) => [$type->value => $type->label()]
                                    )->all())
                                    ->default(PastPaperQuestionType::Mcq->value)
                                    ->required()
                                    ->live(),
                                Textarea::make('question_text')
                                    ->required()
                                    ->columnSpanFull(),
                                KeyValue::make('options')
                                    ->label('MCQ Options (e.g. A, B, C, D)')
                                    ->keyLabel('Option Key (e.g., A)')
                                    ->valueLabel('Option Content')
                                    ->visible(fn (Get $get): bool => $get('type') === PastPaperQuestionType::Mcq->value),
                                TextInput::make('correct_choice')
                                    ->label('Correct Choice Key (e.g., A)')
                                    ->maxLength(10)
                                    ->visible(fn (Get $get): bool => $get('type') === PastPaperQuestionType::Mcq->value),
                                Textarea::make('marking_scheme')
                                    ->label('Marking Scheme / Model Answer')
                                    ->helperText('Expected key points, formula steps, or model answer used for AI grading assistance.')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === PastPaperQuestionType::Structural->value),
                                TextInput::make('max_points')
                                    ->numeric()
                                    ->required()
                                    ->default(10)
                                    ->minValue(1),
                                TextInput::make('order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->orderColumn('order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question_text'] ?? null),
                    ]),
            ]);
    }
}
