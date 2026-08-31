<?php

namespace App\Filament\Resources\Chapters\Schemas;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                Select::make('exam_subsystem')
                    ->label('Exam Subsystem')
                    ->options(collect(ExamSubsystem::cases())->mapWithKeys(
                        fn (ExamSubsystem $subsystem) => [$subsystem->value => $subsystem->label()]
                    )->all())
                    ->placeholder('Inherit from subject or select subsystem')
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
