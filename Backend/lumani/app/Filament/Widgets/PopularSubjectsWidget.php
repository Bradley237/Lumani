<?php

namespace App\Filament\Widgets;

use App\Models\Subject;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PopularSubjectsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected function getTableHeading(): string
    {
        return 'Popular Subjects (Chapter Unlocks This Month)';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subject::query()
                    ->withCount([
                        'chapterUnlocks as monthly_unlocks_count' => function ($query) {
                            $query->where('user_chapter_unlocks.created_at', '>=', now()->startOfMonth());
                        },
                        'chapters',
                    ])
                    ->orderByDesc('monthly_unlocks_count')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Subject')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('exam_subsystem')
                    ->label('Subsystem')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'general' => 'info',
                        'technical' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('monthly_unlocks_count')
                    ->label('Unlocks This Month')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('chapters_count')
                    ->label('Total Chapters')
                    ->sortable(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
