<?php

namespace App\Filament\Resources\CareerProfiles\Tables;

use App\Enums\JobDemand;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CareerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('job_demand')
                    ->badge()
                    ->color(fn (JobDemand $state): string => match ($state) {
                        JobDemand::VeryHigh => 'danger',
                        JobDemand::High => 'warning',
                        JobDemand::Moderate => 'info',
                        JobDemand::Low => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('average_salary')
                    ->searchable(),
                TextColumn::make('related_subjects')
                    ->badge()
                    ->separator(','),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
