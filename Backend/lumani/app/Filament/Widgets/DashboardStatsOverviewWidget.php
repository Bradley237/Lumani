<?php

namespace App\Filament\Widgets;

use App\Enums\ChapterProgressState;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Enums\UserRole;
use App\Models\AiTutorConversation;
use App\Models\ChapterProgress;
use App\Models\CoinTransaction;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Platform Overview';

    protected function getStats(): array
    {
        $startOfWeek = now()->startOfWeek();

        // 1. Total students / total registered this week
        $totalStudents = User::where('role', UserRole::Student)->count();
        $registeredThisWeek = User::where('role', UserRole::Student)
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        // 2. Active subscriptions (broken down by tier: 2,000 FCFA vs 5,000 FCFA)
        $active2k = Subscription::where('status', SubscriptionStatus::Active)
            ->where('end_date', '>', now())
            ->where('tier', SubscriptionTier::Tier2000)
            ->count();
        $active5k = Subscription::where('status', SubscriptionStatus::Active)
            ->where('end_date', '>', now())
            ->where('tier', SubscriptionTier::Tier5000)
            ->count();
        $totalActiveSubs = $active2k + $active5k;

        // 3. Total coins currently in circulation and total coins spent this week
        $totalCoinsCirculation = (int) User::sum('coin_balance');
        $spentThisWeek = abs((int) CoinTransaction::where('amount', '<', 0)
            ->where('created_at', '>=', $startOfWeek)
            ->sum('amount'));

        // 4. Chapters completed this week (from chapter_progress)
        $chaptersCompletedThisWeek = ChapterProgress::where('state', ChapterProgressState::Completed)
            ->where(function ($q) use ($startOfWeek) {
                $q->where('completed_at', '>=', $startOfWeek)
                    ->orWhere(function ($sub) use ($startOfWeek) {
                        $sub->whereNull('completed_at')
                            ->where('updated_at', '>=', $startOfWeek);
                    });
            })
            ->count();

        // 5. Average quiz score across all attempts this week
        $avgScoreThisWeek = QuizAttempt::where('created_at', '>=', $startOfWeek)->avg('score_percent');
        $formattedAvgScore = $avgScoreThisWeek !== null
            ? round((float) $avgScoreThisWeek, 1).'%'
            : 'N/A';

        // 6. AI Tutor conversations started this week
        $tutorConversationsThisWeek = AiTutorConversation::where('created_at', '>=', $startOfWeek)->count();

        return [
            Stat::make('Total Students', number_format($totalStudents))
                ->description("+{$registeredThisWeek} registered this week")
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary'),

            Stat::make('Active Subscriptions', number_format($totalActiveSubs))
                ->description("2k FCFA: {$active2k} | 5k FCFA: {$active5k}")
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('success'),

            Stat::make('Coins in Circulation', number_format($totalCoinsCirculation))
                ->description(number_format($spentThisWeek).' spent this week')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color('warning'),

            Stat::make('Chapters Completed', number_format($chaptersCompletedThisWeek))
                ->description('Completed this week')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('success'),

            Stat::make('Avg Quiz Score', $formattedAvgScore)
                ->description('Across attempts this week')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('info'),

            Stat::make('AI Tutor Conversations', number_format($tutorConversationsThisWeek))
                ->description('Started this week')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('primary'),
        ];
    }
}
