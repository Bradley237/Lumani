<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                    ->options([
                        'anglophone' => 'Anglophone Subsystem',
                        'francophone' => 'Francophone Subsystem',
                        'general' => 'General / Both',
                    ])
                    ->placeholder('Select exam subsystem (optional)'),
            ]);
    }
}
