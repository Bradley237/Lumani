<?php

namespace App\Filament\Resources\WeeklyChallenges\Schemas;

use App\Enums\ChallengeQuestionType;
use App\Enums\ChallengeStatus;
use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class WeeklyChallengeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Challenge Details')
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
                            ->label('Exam Subsystem')
                            ->options(collect(ExamSubsystem::cases())->mapWithKeys(
                                fn (ExamSubsystem $subsystem) => [$subsystem->value => $subsystem->label()]
                            )->all())
                            ->placeholder('Select exam subsystem (optional)')
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('level', null)),
                        Select::make('level')
                            ->label('Exam Level')
                            ->options(function (Get $get) {
                                $subsystemRaw = $get('exam_subsystem');
                                $subsystem = $subsystemRaw instanceof ExamSubsystem
                                    ? $subsystemRaw
                                    : ($subsystemRaw ? ExamSubsystem::tryFrom((string) $subsystemRaw) : null);

                                if (! $subsystem) {
                                    return [];
                                }

                                return collect($subsystem->validLevels())->mapWithKeys(
                                    fn (ExamLevel $level) => [$level->value => $level->label()]
                                )->all();
                            })
                            ->placeholder('Select academic level (optional)')
                            ->disabled(fn (Get $get): bool => blank($get('exam_subsystem'))),
                        TextInput::make('time_limit_minutes')
                            ->numeric()
                            ->required()
                            ->default(30)
                            ->minValue(5)
                            ->helperText('Duration in minutes allowed for student attempts'),
                        Select::make('status')
                            ->options(collect(ChallengeStatus::cases())->mapWithKeys(
                                fn (ChallengeStatus $status) => [$status->value => $status->label()]
                            )->all())
                            ->default(ChallengeStatus::Draft->value)
                            ->required(),
                        DateTimePicker::make('week_start_date')
                            ->required()
                            ->default(now()->startOfWeek()),
                        DateTimePicker::make('week_end_date')
                            ->required()
                            ->default(now()->endOfWeek()),
                    ]),
                Section::make('Challenge Questions')
                    ->description('Add Multiple Choice or Structural/Essay questions for this challenge.')
                    ->components([
                        Repeater::make('questions')
                            ->relationship('questions')
                            ->defaultItems(0)
                            ->addActionLabel('Add Question')
                            ->schema([
                                Select::make('type')
                                    ->options(collect(ChallengeQuestionType::cases())->mapWithKeys(
                                        fn (ChallengeQuestionType $type) => [$type->value => $type->label()]
                                    )->all())
                                    ->default(ChallengeQuestionType::Mcq->value)
                                    ->required()
                                    ->live(),
                                Textarea::make('question_text')
                                    ->required()
                                    ->columnSpanFull(),
                                KeyValue::make('options')
                                    ->label('MCQ Options (e.g. A, B, C, D)')
                                    ->keyLabel('Option Key (e.g., A)')
                                    ->valueLabel('Option Content')
                                    ->visible(fn (Get $get): bool => $get('type') === ChallengeQuestionType::Mcq->value),
                                TextInput::make('correct_choice')
                                    ->label('Correct Choice Key (e.g., A)')
                                    ->maxLength(10)
                                    ->visible(fn (Get $get): bool => $get('type') === ChallengeQuestionType::Mcq->value),
                                Textarea::make('marking_scheme')
                                    ->label('Marking Scheme / Model Answer')
                                    ->helperText('Expected key points, formula steps, or model answer used for AI grading assistance.')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === ChallengeQuestionType::Structural->value),
                                TextInput::make('max_points')
                                    ->numeric()
                                    ->required()
                                    ->default(10)
                                    ->minValue(1),
                                TextInput::make('order')
                                    ->numeric()
                                    ->default(0),
                                FileUpload::make('image_path')
                                    ->label('Question Image (optional)')
                                    ->helperText('Resize ≤ 1200 px wide, JPEG q80 applied automatically.')
                                    ->disk('public')
                                    ->directory('question-images')
                                    ->visibility('public')
                                    ->image()
                                    ->imagePreviewHeight('120')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                    ->maxSize(20480)
                                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                        return app(\App\Services\ImageProcessingService::class)->processAndStore($file);
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->orderColumn('order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question_text'] ?? null),
                    ]),
            ]);
    }
}
