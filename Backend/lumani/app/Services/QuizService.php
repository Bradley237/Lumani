<?php

namespace App\Services;

use App\Enums\ChapterProgressState;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function __construct(
        protected AccessControlService $accessControlService,
        protected XpService $xpService
    ) {}

    /**
     * Touch a chapter: mark state as in_progress and update last_accessed_at.
     */
    public function touchChapter(User $user, Chapter $chapter): ChapterProgress
    {
        if (! $this->accessControlService->canAccessChapter($user, $chapter)) {
            throw ValidationException::withMessages([
                'chapter' => 'You must unlock this chapter before accessing it.',
            ]);
        }

        /** @var ChapterProgress $progress */
        $progress = ChapterProgress::firstOrNew([
            'user_id' => $user->id,
            'chapter_id' => $chapter->id,
        ]);

        if ($progress->state !== ChapterProgressState::Completed) {
            $progress->state = ChapterProgressState::InProgress;
        }

        $progress->last_accessed_at = now();
        $progress->save();

        return $progress;
    }

    /**
     * Fetch quiz questions sanitized for the student (omitting correct_choice and explanation).
     *
     * @return array{
     *     id: int,
     *     chapter_id: int,
     *     passing_score: int,
     *     total_questions: int,
     *     questions: array<int, array{id: int, question_text: string, answer_choices: array<string, mixed>|list<mixed>}>
     * }
     */
    public function getQuizForStudent(User $user, Quiz $quiz): array
    {
        $chapter = $quiz->chapter;

        if (! $this->accessControlService->canAccessChapter($user, $chapter)) {
            throw ValidationException::withMessages([
                'chapter' => 'You must unlock this chapter before accessing its quiz.',
            ]);
        }

        $questions = $quiz->questions()
            ->get()
            ->map(function (Question $question): array {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'answer_choices' => $question->answer_choices,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $quiz->id,
            'chapter_id' => $quiz->chapter_id,
            'passing_score' => $quiz->passing_score,
            'total_questions' => count($questions),
            'questions' => $questions,
        ];
    }

    /**
     * Submit a quiz attempt with student answers, grade them, award XP, update chapter progress.
     *
     * @param  array<int, array{question_id: int, selected_choice: string|null}>  $answers
     * @return array{
     *     attempt_id: int,
     *     score_percent: float,
     *     correct_count: int,
     *     total_questions: int,
     *     quiz_xp_earned: int,
     *     chapter_xp_reward: int,
     *     total_xp_earned: int,
     *     is_first_completion: bool,
     *     coins_earned_from_xp: int,
     *     experience_points: int,
     *     coin_balance: int,
     *     chapter_progress: array<string, mixed>,
     *     answers: list<array<string, mixed>>
     * }
     */
    public function submitQuiz(User $user, Quiz $quiz, array $answers): array
    {
        $chapter = $quiz->chapter;

        if (! $this->accessControlService->canAccessChapter($user, $chapter)) {
            throw ValidationException::withMessages([
                'chapter' => 'You must unlock this chapter before taking its quiz.',
            ]);
        }

        return DB::transaction(function () use ($user, $quiz, $chapter, $answers): array {
            $questions = $quiz->questions()->get();
            $answersMap = collect($answers)->keyBy('question_id');

            $correctCount = 0;
            $totalQuestions = $questions->count();
            $gradedDetails = [];

            foreach ($questions as $question) {
                $submitted = $answersMap->get($question->id);
                $selectedChoice = $submitted['selected_choice'] ?? null;

                $isCorrect = false;
                if ($selectedChoice !== null && strtoupper(trim((string) $selectedChoice)) === strtoupper(trim((string) $question->correct_choice))) {
                    $isCorrect = true;
                    $correctCount++;
                }

                $gradedDetails[] = [
                    'question_id' => $question->id,
                    'selected_choice' => $selectedChoice,
                    'is_correct' => $isCorrect,
                    'correct_choice' => $question->correct_choice,
                    'explanation' => $question->explanation,
                ];
            }

            $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;

            // 10 XP per correct answer + flat 20 XP completion bonus
            $quizXp = ($correctCount * 10) + 20;

            // Check if this is the first time the chapter reaches completed state
            /** @var ChapterProgress|null $existingProgress */
            $existingProgress = ChapterProgress::where('user_id', $user->id)
                ->where('chapter_id', $chapter->id)
                ->first();

            $isFirstCompletion = ($existingProgress === null || $existingProgress->state !== ChapterProgressState::Completed);

            $chapterXpReward = 0;
            if ($isFirstCompletion) {
                $chapterXpReward = (int) ($chapter->xp_reward ?? 0);
            }

            $totalXpToAward = $quizXp + $chapterXpReward;

            // Create QuizAttempt record
            /** @var QuizAttempt $attempt */
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'score_percent' => $scorePercent,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'xp_earned' => $quizXp,
                'submitted_at' => now(),
            ]);

            // Save individual answers
            foreach ($gradedDetails as $detail) {
                QuizAttemptAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'question_id' => $detail['question_id'],
                    'selected_choice' => $detail['selected_choice'],
                    'is_correct' => $detail['is_correct'],
                ]);
            }

            // Upsert ChapterProgress to completed
            $now = now();
            $progress = ChapterProgress::firstOrNew([
                'user_id' => $user->id,
                'chapter_id' => $chapter->id,
            ]);

            $progress->state = ChapterProgressState::Completed;
            $progress->last_accessed_at = $now;
            if ($progress->completed_at === null) {
                $progress->completed_at = $now;
            }
            $progress->save();

            // Award XP atomically (and handle any 1,500 XP chunk coin conversions)
            $xpResult = $this->xpService->award($user, $totalXpToAward, $attempt);

            return [
                'attempt_id' => $attempt->id,
                'score_percent' => $scorePercent,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'quiz_xp_earned' => $quizXp,
                'chapter_xp_reward' => $chapterXpReward,
                'total_xp_earned' => $totalXpToAward,
                'is_first_completion' => $isFirstCompletion,
                'coins_earned_from_xp' => $xpResult['coins_converted'],
                'experience_points' => $xpResult['experience_points'],
                'coin_balance' => $xpResult['coin_balance'],
                'chapter_progress' => [
                    'chapter_id' => $progress->chapter_id,
                    'state' => $progress->state->value,
                    'completed_at' => $progress->completed_at->toIso8601String(),
                    'last_accessed_at' => $progress->last_accessed_at->toIso8601String(),
                ],
                'answers' => $gradedDetails,
            ];
        });
    }

    /**
     * Get overall chapter progress across all subjects and chapters for a student.
     *
     * @return array<string, mixed>
     */
    public function getStudentProgress(User $user): array
    {
        $subjects = Subject::with(['chapters' => fn ($q) => $q->orderBy('order', 'asc')])->get();
        $userProgress = ChapterProgress::where('user_id', $user->id)->get()->keyBy('chapter_id');
        $userUnlocks = $user->chapterUnlocks()->pluck('chapter_id')->flip();
        $freeMode = AppSetting::isFreeModeEnabled();

        $totalChapters = 0;
        $completedChapters = 0;
        $inProgressChapters = 0;

        $subjectData = $subjects->map(function (Subject $subject) use ($userProgress, $userUnlocks, $freeMode, &$totalChapters, &$completedChapters, &$inProgressChapters): array {
            $subTotal = $subject->chapters->count();
            $subCompleted = 0;

            $chaptersData = $subject->chapters->map(function (Chapter $chapter) use ($userProgress, $userUnlocks, $freeMode, &$totalChapters, &$completedChapters, &$inProgressChapters, &$subCompleted): array {
                $totalChapters++;
                /** @var ChapterProgress|null $prog */
                $prog = $userProgress->get($chapter->id);
                $state = $prog ? $prog->state->value : ChapterProgressState::NotStarted->value;

                if ($state === ChapterProgressState::Completed->value) {
                    $completedChapters++;
                    $subCompleted++;
                } elseif ($state === ChapterProgressState::InProgress->value) {
                    $inProgressChapters++;
                }

                $isUnlocked = $freeMode || $chapter->is_free || isset($userUnlocks[$chapter->id]);

                return [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'order' => $chapter->order,
                    'is_free' => $chapter->is_free,
                    'is_unlocked' => $isUnlocked,
                    'coin_price' => $chapter->coin_price,
                    'xp_reward' => $chapter->xp_reward,
                    'state' => $state,
                    'last_accessed_at' => $prog?->last_accessed_at?->toIso8601String(),
                    'completed_at' => $prog?->completed_at?->toIso8601String(),
                ];
            })->values()->all();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'total_chapters' => $subTotal,
                'completed_chapters' => $subCompleted,
                'chapters' => $chaptersData,
            ];
        })->values()->all();

        $overallPercent = $totalChapters > 0 ? round(($completedChapters / $totalChapters) * 100, 2) : 0.0;

        return [
            'total_chapters' => $totalChapters,
            'completed_chapters' => $completedChapters,
            'in_progress_chapters' => $inProgressChapters,
            'overall_progress_percent' => $overallPercent,
            'experience_points' => (int) $user->experience_points,
            'coin_balance' => (int) $user->coin_balance,
            'subjects' => $subjectData,
        ];
    }
}
