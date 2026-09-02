<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\ExamLevel;
use App\Enums\ExamSubsystem;
use App\Enums\UserRole;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->badge()
                    ->color(fn (UserRole $state) => match ($state) {
                        UserRole::Admin   => 'warning',
                        UserRole::Student => 'primary',
                    }),

                TextColumn::make('coin_balance')
                    ->label('Coins')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('experience_points')
                    ->label('XP')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('day_streak')
                    ->label('Streak')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('exam_system')
                    ->label('Exam System')
                    ->formatStateUsing(fn (?ExamSubsystem $state) => $state?->name ?? '—')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('level')
                    ->label('Level')
                    ->formatStateUsing(fn (?ExamLevel $state) => $state?->name ?? '—')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()])
                            ->toArray()
                    ),

                SelectFilter::make('exam_system')
                    ->label('Exam System')
                    ->options(
                        collect(ExamSubsystem::cases())
                            ->mapWithKeys(fn (ExamSubsystem $e) => [$e->value => $e->name])
                            ->toArray()
                    ),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
