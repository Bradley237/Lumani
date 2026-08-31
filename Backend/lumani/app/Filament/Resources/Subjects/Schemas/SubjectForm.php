<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
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
            ]);
    }
}
