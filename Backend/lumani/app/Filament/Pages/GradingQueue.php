<?php

namespace App\Filament\Pages;

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeQuestionType;
use App\Enums\ExamSessionStatus;
use App\Enums\PastPaperQuestionType;
use App\Models\ExamSessionAnswer;
use App\Models\User;
use App\Models\UserChallengeAnswer;
use App\Services\ChallengeService;
use App\Services\ExamSessionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GradingQueue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Grading Queue';

    protected static ?string $slug = 'grading-queue';

    protected string $view = 'filament.pages.grading-queue';

    /**
     * @var array<int, int|string>
     */
    public array $grades = [];

    /**
     * @var array<int, int|string>
     */
    public array $examGrades = [];

    public function mount(): void
    {
        $this->loadUngradedAnswers();
    }

    public function loadUngradedAnswers(): void
    {
        $ungradedChallenges = $this->getUngradedAnswers();
        foreach ($ungradedChallenges as $ans) {
            if (! isset($this->grades[$ans->id])) {
                $this->grades[$ans->id] = $ans->suggested_points !== null ? (string) $ans->suggested_points : '';
            }
        }

        $ungradedExams = $this->getUngradedExamAnswers();
        foreach ($ungradedExams as $ans) {
            if (! isset($this->examGrades[$ans->id])) {
                $this->examGrades[$ans->id] = $ans->suggested_points !== null ? (string) $ans->suggested_points : '';
            }
        }
    }

    /**
     * @return Collection<int, UserChallengeAnswer>
     */
    public function getUngradedAnswers()
    {
        return UserChallengeAnswer::whereNull('points_awarded')
            ->whereHas('question', function ($q) {
                $q->where('type', ChallengeQuestionType::Structural->value);
            })
            ->whereHas('attempt', function ($q) {
                $q->where('status', ChallengeAttemptStatus::Submitted->value);
            })
            ->with(['attempt.user', 'attempt.challenge', 'question'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ExamSessionAnswer>
     */
    public function getUngradedExamAnswers()
    {
        return ExamSessionAnswer::whereNull('points_awarded')
            ->whereHas('question', function ($q) {
                $q->where('type', PastPaperQuestionType::Structural->value);
            })
            ->whereHas('session', function ($q) {
                $q->where('status', ExamSessionStatus::Submitted->value);
            })
            ->with(['session.user', 'session.pastPaper.subject', 'question'])
            ->latest('created_at')
            ->get();
    }

    public function gradeAnswer(int $answerId, ChallengeService $challengeService): void
    {
        /** @var User $admin */
        $admin = Auth::user();

        /** @var UserChallengeAnswer|null $answer */
        $answer = UserChallengeAnswer::with(['question', 'attempt.user', 'attempt.challenge'])->find($answerId);

        if (! $answer) {
            Notification::make()->title('Answer not found.')->danger()->send();

            return;
        }

        $points = $this->grades[$answerId] ?? null;

        if ($points === null || $points === '' || ! is_numeric($points)) {
            Notification::make()->title('Please enter a valid points number.')->warning()->send();

            return;
        }

        $intPoints = (int) $points;

        if ($intPoints < 0 || $intPoints > $answer->question->max_points) {
            Notification::make()
                ->title("Points must be between 0 and {$answer->question->max_points}.")
                ->danger()
                ->send();

            return;
        }

        $result = $challengeService->gradeStructuralAnswer($admin, $answer, $intPoints);

        unset($this->grades[$answerId]);

        $message = "Graded {$intPoints} / {$answer->question->max_points} points.";
        if ($result['attempt_status'] === ChallengeAttemptStatus::Graded->value) {
            $score = $result['total_score_percent'] ?? 0;
            $coins = $result['reward_coins_awarded'] ?? 0;
            $message .= " Attempt finalized: Score {$score}%, Coins: {$coins}.";
        }

        Notification::make()
            ->title('Weekly Challenge Answer Graded')
            ->body($message)
            ->success()
            ->send();
    }

    public function gradeExamAnswer(int $answerId, ExamSessionService $examSessionService): void
    {
        /** @var User $admin */
        $admin = Auth::user();

        /** @var ExamSessionAnswer|null $answer */
        $answer = ExamSessionAnswer::with(['question', 'session.user', 'session.pastPaper'])->find($answerId);

        if (! $answer) {
            Notification::make()->title('Answer not found.')->danger()->send();

            return;
        }

        $points = $this->examGrades[$answerId] ?? null;

        if ($points === null || $points === '' || ! is_numeric($points)) {
            Notification::make()->title('Please enter a valid points number.')->warning()->send();

            return;
        }

        $intPoints = (int) $points;

        if ($intPoints < 0 || $intPoints > $answer->question->max_points) {
            Notification::make()
                ->title("Points must be between 0 and {$answer->question->max_points}.")
                ->danger()
                ->send();

            return;
        }

        $result = $examSessionService->gradeStructuralAnswer($admin, $answer, $intPoints);

        unset($this->examGrades[$answerId]);

        $message = "Graded {$intPoints} / {$answer->question->max_points} points.";
        if ($result['session_status'] === ExamSessionStatus::Graded->value) {
            $score = $result['total_score_percent'] ?? 0;
            $message .= " Exam session finalized: Score {$score}%.";
        }

        Notification::make()
            ->title('Exam Session Answer Graded')
            ->body($message)
            ->success()
            ->send();
    }
}
