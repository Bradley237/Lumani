<?php

namespace App\Filament\Resources\PastPapers\Tables;

use App\Models\PastPaper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PastPapersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->sortable(),
                TextColumn::make('exam_subsystem')
                    ->badge()
                    ->sortable(),
                TextColumn::make('coin_price')
                    ->label('Paper Coins')
                    ->numeric()
                    ->sortable()
                    ->badge(),
                IconColumn::make('file_path')
                    ->label('Paper PDF')
                    ->boolean()
                    ->state(fn (PastPaper $record): bool => ! blank($record->file_path)),
                TextColumn::make('solution_coin_price')
                    ->label('Solution Coins')
                    ->numeric()
                    ->sortable()
                    ->badge(),
                IconColumn::make('solution_file_path')
                    ->label('Solution PDF')
                    ->boolean()
                    ->state(fn (PastPaper $record): bool => ! blank($record->solution_file_path)),
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
