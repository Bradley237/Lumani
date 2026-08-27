<?php

namespace App\Filament\Resources\CareerProfiles\Schemas;

use App\Enums\JobDemand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CareerProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Career Information')
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Select::make('job_demand')
                            ->options(collect(JobDemand::cases())->mapWithKeys(
                                fn (JobDemand $demand) => [$demand->value => $demand->label()]
                            )->all())
                            ->default(JobDemand::Moderate->value)
                            ->required(),
                        TextInput::make('average_salary')
                            ->label('Average Salary / Compensation')
                            ->placeholder('e.g., 600,000 - 1,500,000 FCFA / month')
                            ->maxLength(255),
                        TagsInput::make('related_subjects')
                            ->label('Related Subjects / Fields')
                            ->placeholder('Add subject name (e.g. Mathematics, Physics, Chemistry)')
                            ->helperText('Type a subject name and press enter')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
