<?php

namespace App\Filament\Resources\PastPapers\Schemas;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use App\Enums\PastPaperQuestionType;
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
                        FileUpload::make('file_path')
                            ->label('Past Paper Document (PDF)')
                            ->disk('local')
                            ->directory('past-papers/questions')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(51200)
                            ->downloadable()
                            ->openable()
                            ->helperText('Upload the questions PDF document (stored securely on private disk).'),
                        TextInput::make('solution_coin_price')
                            ->required()
                            ->numeric()
                            ->default(20)
                            ->minValue(0)
                            ->helperText('Coin cost to unlock solutions document'),
                        FileUpload::make('solution_file_path')
                            ->label('Solution Document (PDF)')
                            ->disk('local')
                            ->directory('past-papers/solutions')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(51200)
                            ->downloadable()
                            ->openable()
                            ->helperText('Upload the worked solutions PDF document (stored securely on private disk).'),
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
