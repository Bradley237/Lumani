<?php

namespace App\Filament\Resources\Questions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('quiz_id')
                    ->relationship('quiz', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Quiz #{$record->id} (Chapter: ".($record->chapter->title ?? 'N/A').')')
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
                    ->helperText('Upload an image to accompany this question. Max 20 MB — it will be automatically resized to ≤1200 px wide and compressed to JPEG.')
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
            ]);
    }
}
