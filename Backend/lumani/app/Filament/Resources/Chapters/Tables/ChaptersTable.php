<?php

namespace App\Filament\Resources\Chapters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChaptersTable
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
                TextColumn::make('exam_subsystem')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('level')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coin_price')
                    ->label('Coin Price')
                    ->numeric()
                    ->sortable()
                    ->badge(),
                TextColumn::make('xp_reward')
                    ->label('XP Reward')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('quizzes_count')
                    ->counts('quizzes')
                    ->label('Quizzes')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
