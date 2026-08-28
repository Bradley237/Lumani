<?php

namespace App\Filament\Resources\SubmittedQuestions\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\SubmittedQuestions\SubmittedQuestionResource;
use App\Models\SubmittedQuestion;
use App\Services\ContentReviewService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditSubmittedQuestion extends EditRecord
{
    protected static string $resource = SubmittedQuestionResource::class;

    protected function getHeaderActions(): array
    {
        /** @var SubmittedQuestion $record */
        $record = $this->getRecord();

        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('info')
                ->visible(fn (): bool => in_array($record->review_status, [ReviewStatus::Pending, ReviewStatus::Rejected]))
                ->requiresConfirmation()
                ->modalHeading('Approve Question')
                ->modalDescription('Are you sure you want to approve this question for publication?')
                ->action(function (ContentReviewService $service) use ($record) {
                    $service->approve(Auth::user(), $record);
                    $this->refreshFormData(['review_status', 'reviewed_by']);
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
                ->visible(fn (): bool => $record->review_status !== ReviewStatus::Published && $record->review_status !== ReviewStatus::Rejected)
                ->modalHeading('Reject Question')
                ->modalDescription('Please provide clear notes explaining why this question is being rejected.')
                ->form([
                    Textarea::make('review_notes')
                        ->label('Rejection Notes')
                        ->required()
                        ->rows(3)
                        ->placeholder('e.g., Question text is ambiguous or correct answer is inaccurate.'),
                ])
                ->action(function (array $data, ContentReviewService $service) use ($record) {
                    $service->reject(Auth::user(), $record, $data['review_notes']);
                    $this->refreshFormData(['review_status', 'reviewed_by', 'review_notes']);
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
                ->visible(fn (): bool => $record->review_status === ReviewStatus::Approved)
                ->requiresConfirmation()
                ->modalHeading('Publish Question to Live Quizzes')
                ->modalDescription('This will create an active question record in the chapter quiz.')
                ->action(function (ContentReviewService $service) use ($record) {
                    $question = $service->publish(Auth::user(), $record);
                    $this->refreshFormData(['review_status', 'reviewed_by']);
                    Notification::make()
                        ->title('Question Published')
                        ->body("Live Question #{$question->id} created successfully.")
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
