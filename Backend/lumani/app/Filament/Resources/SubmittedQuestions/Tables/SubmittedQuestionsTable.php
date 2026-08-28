<?php

namespace App\Filament\Resources\SubmittedQuestions\Tables;

use App\Enums\ReviewStatus;
use App\Models\SubmittedQuestion;
use App\Services\ContentReviewService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubmittedQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('chapter.title')
                    ->label('Chapter')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('question_text')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('correct_choice')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('review_status')
                    ->badge()
                    ->color(fn (ReviewStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ReviewStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('submitter.name')
                    ->label('Submitted By')
                    ->placeholder('Admin / System')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('Not Reviewed')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('review_status')
                    ->label('Review Status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->visible(fn (SubmittedQuestion $record): bool => in_array($record->review_status, [ReviewStatus::Pending, ReviewStatus::Rejected]))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Question')
                    ->modalDescription('Are you sure you want to approve this question for publication?')
                    ->action(function (SubmittedQuestion $record, ContentReviewService $service) {
                        $service->approve(Auth::user(), $record);
                        Notification::make()
                            ->title('Question Approved')
                            ->body("Question #{$record->id} has been approved.")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (SubmittedQuestion $record): bool => $record->review_status !== ReviewStatus::Published && $record->review_status !== ReviewStatus::Rejected)
                    ->modalHeading('Reject Question')
                    ->modalDescription('Please provide clear notes explaining why this question is being rejected.')
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Rejection Notes')
                            ->required()
                            ->rows(3)
                            ->placeholder('e.g., Question text is ambiguous or correct answer is inaccurate.'),
                    ])
                    ->action(function (SubmittedQuestion $record, array $data, ContentReviewService $service) {
                        $service->reject(Auth::user(), $record, $data['review_notes']);
                        Notification::make()
                            ->title('Question Rejected')
                            ->body("Question #{$record->id} has been rejected.")
                            ->warning()
                            ->send();
                    }),
                Action::make('publish')
                    ->label('Publish')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn (SubmittedQuestion $record): bool => $record->review_status === ReviewStatus::Approved)
                    ->requiresConfirmation()
                    ->modalHeading('Publish Question to Live Quizzes')
                    ->modalDescription('This will create an active question record in the chapter quiz.')
                    ->action(function (SubmittedQuestion $record, ContentReviewService $service) {
                        $question = $service->publish(Auth::user(), $record);
                        Notification::make()
                            ->title('Question Published')
                            ->body("Live Question #{$question->id} created successfully.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
