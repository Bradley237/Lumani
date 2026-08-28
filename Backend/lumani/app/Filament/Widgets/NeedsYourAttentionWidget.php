<?php

namespace App\Filament\Widgets;

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeQuestionType;
use App\Enums\ExamSessionStatus;
use App\Enums\PastPaperQuestionType;
use App\Enums\ReviewStatus;
use App\Filament\Pages\GradingQueue;
use App\Filament\Resources\SubmittedQuestions\SubmittedQuestionResource;
use App\Models\ExamSessionAnswer;
use App\Models\SubmittedQuestion;
use App\Models\UserChallengeAnswer;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NeedsYourAttentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Needs Your Attention';

    protected function getStats(): array
    {
        $pendingQuestionsCount = SubmittedQuestion::where('review_status', ReviewStatus::Pending)->count();

        $ungradedChallengesCount = UserChallengeAnswer::whereNull('points_awarded')
            ->whereHas('question', function ($q) {
                $q->where('type', ChallengeQuestionType::Structural->value);
            })
            ->whereHas('attempt', function ($q) {
                $q->where('status', ChallengeAttemptStatus::Submitted->value);
            })
            ->count();

        $ungradedExamsCount = ExamSessionAnswer::whereNull('points_awarded')
            ->whereHas('question', function ($q) {
                $q->where('type', PastPaperQuestionType::Structural->value);
            })
            ->whereHas('session', function ($q) {
                $q->where('status', ExamSessionStatus::Submitted->value);
            })
            ->count();

        $totalUngradedCount = $ungradedChallengesCount + $ungradedExamsCount;

        return [
            Stat::make('Pending Questions Review', number_format($pendingQuestionsCount))
                ->description($pendingQuestionsCount > 0 ? 'Questions submitted by students awaiting review' : 'All submitted questions reviewed')
                ->descriptionIcon($pendingQuestionsCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($pendingQuestionsCount > 0 ? 'warning' : 'success')
                ->url(SubmittedQuestionResource::getUrl('index')),

            Stat::make('Ungraded Structural Answers', number_format($totalUngradedCount))
                ->description($totalUngradedCount > 0 ? "Weekly Challenges: {$ungradedChallengesCount} | Exams: {$ungradedExamsCount}" : 'Grading queue is completely clear')
                ->descriptionIcon($totalUngradedCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($totalUngradedCount > 0 ? 'danger' : 'success')
                ->url(GradingQueue::getUrl()),
        ];
    }
}
