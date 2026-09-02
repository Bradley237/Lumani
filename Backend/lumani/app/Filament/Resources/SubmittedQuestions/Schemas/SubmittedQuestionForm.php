<?php

namespace App\Filament\Resources\SubmittedQuestions\Schemas;

use App\Enums\ReviewStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SubmittedQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chapter_id')
                    ->relationship('chapter', 'title')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Chapter #{$record->id}: {$record->title} (".($record->subject->name ?? 'N/A').')')
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
                FileUpload::make('image_path')
                    ->label('Question Image (optional)')
                    ->helperText('Upload an image to support this question. Max 20 MB — resized to ≤1200 px wide and saved as JPEG automatically.')
                    ->disk('public')
                    ->directory('question-images')
                    ->visibility('public')
                    ->image()
                    ->imagePreviewHeight('160')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->maxSize(20480)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                        return app(\App\Services\ImageProcessingService::class)->processAndStore($file);
                    })
                    ->columnSpanFull(),
                Select::make('review_status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()]))
                    ->default(ReviewStatus::Pending->value)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit']),
                Select::make('submitted_by')
                    ->relationship('submitter', 'email')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit']),
                Select::make('reviewed_by')
                    ->relationship('reviewer', 'email')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit']),
                Textarea::make('review_notes')
                    ->rows(2)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->visibleOn(['edit']),
            ]);
    }
}
