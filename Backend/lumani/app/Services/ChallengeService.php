<?php

namespace App\Services;

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeQuestionType;
use App\Enums\ChallengeStatus;
use App\Enums\CoinTransactionType;
use App\Models\User;
use App\Models\UserChallengeAnswer;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ChallengeService
{
    public function __construct(
        protected CoinService $coinService,
        protected GradingAssistantService $gradingAssistantService,
    ) {}

    /**
     * Start a weekly challenge attempt for a student.
     */
    public function startAttempt(User $user, WeeklyChallenge $challenge): UserChallengeAttempt
    {
        if ($challenge->status !== ChallengeStatus::Published) {
            throw ValidationException::withMessages([
                'challenge' => 'This challenge is not published.',
            ]);
        }

        if (! $challenge->isWithinWeekWindow()) {
            throw ValidationException::withMessages([
                'challenge' => 'This challenge is outside its active week window.',
            ]);
        }

        $existingAttempt = UserChallengeAttempt::where('user_id', $user->id)
            ->where('weekly_challenge_id', $challenge->id)
            ->first();

        if ($existingAttempt) {
            throw ValidationException::withMessages([
                'challenge' => 'You have already started or attempted this challenge.',
            ]);
        }

        return UserChallengeAttempt::create([
            'user_id' => $user->id,
            'weekly_challenge_id' => $challenge->id,
            'started_at' => now(),
            'status' => ChallengeAttemptStatus::InProgress,
        ]);
    }

    /**
     * Submit an attempt with answers.
     *
     * @param  array<int, array{question_id: int, selected_choice?: string|null, answer_text?: string|null}>  $answers
     * @return array{
     *     status: string,
     *     message: string,
     *     total_score_percent?: float|null,
     *     reward_coins_awarded?: int|null,
     *     coin_balance?: int
     * }
     */
    public function submitAttempt(User $user, UserChallengeAttempt $attempt, array $answers): array
    {
        if ($attempt->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'attempt' => 'Unauthorized attempt submission.',
            ]);
        }

        if ($attempt->status !== ChallengeAttemptStatus::InProgress) {
            throw ValidationException::withMessages([
                'attempt' => 'This challenge attempt has already been submitted.',
            ]);
        }

        $challenge = $attempt->challenge;
        $startedAt = Carbon::parse($attempt->started_at);
        $deadline = $startedAt->copy()->addMinutes($challenge->time_limit_minutes)->addSeconds(60);

        if (now()->gt($deadline)) {
            throw ValidationException::withMessages([
                'time_limit' => 'Time limit exceeded for this challenge attempt.',
            ]);
        }

        return DB::transaction(function () use ($user, $attempt, $challenge, $answers): array {
            $questions = $challenge->questions()->get();
            $answersMap = collect($answers)->keyBy('question_id');

            $hasStructural = false;
            $totalEarnedMcqPoints = 0;
            $totalMaxPoints = 0;

            foreach ($questions as $question) {
                $ansData = $answersMap->get($question->id);
                $selectedChoice = $ansData['selected_choice'] ?? null;
                $answerText = $ansData['answer_text'] ?? null;
                $pointsAwarded = null;

                $suggestedPoints = null;
                $suggestedJustification = null;

                if ($question->type === ChallengeQuestionType::Mcq) {
                    if ($selectedChoice !== null && strtoupper(trim((string) $selectedChoice)) === strtoupper(trim((string) $question->correct_choice))) {
                        $pointsAwarded = $question->max_points;
                    } else {
                        $pointsAwarded = 0;
                    }
                    $totalEarnedMcqPoints += $pointsAwarded;
                } else {
                    $hasStructural = true;
                    $pointsAwarded = null;

                    if ($answerText !== null && trim((string) $answerText) !== '') {
                        $suggestion = $this->gradingAssistantService->suggestScore($question, (string) $answerText);
                        if ($suggestion !== null) {
                            $suggestedPoints = $suggestion['suggested_points'];
                            $suggestedJustification = $suggestion['suggested_justification'];
                        }
                    }
                }

                $totalMaxPoints += $question->max_points;

                UserChallengeAnswer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'selected_choice' => $selectedChoice,
                        'answer_text' => $answerText,
                        'points_awarded' => $pointsAwarded,
                        'suggested_points' => $suggestedPoints,
                        'suggested_justification' => $suggestedJustification,
                    ]
                );
            }

            if (! $hasStructural) {
                $scorePercent = $totalMaxPoints > 0 ? round(($totalEarnedMcqPoints / $totalMaxPoints) * 100, 2) : 0.0;
                $coins = $this->calculateCoinsForScore($scorePercent);

                $attempt->status = ChallengeAttemptStatus::Graded;
                $attempt->submitted_at = now();
                $attempt->total_score_percent = $scorePercent;
                $attempt->reward_coins_awarded = $coins;
                $attempt->save();

                if ($coins > 0) {
                    $this->coinService->award($user, $coins, CoinTransactionType::EarnedChallenge, $challenge);
                }

                return [
                    'status' => $attempt->status->value,
                    'message' => 'Challenge submitted and graded successfully.',
                    'total_score_percent' => $scorePercent,
                    'reward_coins_awarded' => $coins,
                    'coin_balance' => $this->coinService->getBalance($user),
                ];
            }

            $attempt->status = ChallengeAttemptStatus::Submitted;
            $attempt->submitted_at = now();
            $attempt->total_score_percent = null;
            $attempt->reward_coins_awarded = null;
            $attempt->save();

            return [
                'status' => $attempt->status->value,
                'message' => 'Challenge submitted successfully. Results will be available after teacher grading.',
                'total_score_percent' => null,
                'reward_coins_awarded' => null,
            ];
        });
    }

    /**
     * Grade a structural question answer.
     *
     * @return array{
     *     answer_id: int,
     *     points_awarded: int,
     *     attempt_status: string,
     *     total_score_percent?: float|null,
     *     reward_coins_awarded?: int|null
     * }
     */
    public function gradeStructuralAnswer(User $admin, UserChallengeAnswer $answer, int $pointsAwarded): array
    {
        $question = $answer->question;

        if ($pointsAwarded < 0 || $pointsAwarded > $question->max_points) {
            throw new InvalidArgumentException("Points awarded must be between 0 and {$question->max_points}.");
        }

        $answer->points_awarded = $pointsAwarded;
        $answer->save();

        /** @var UserChallengeAttempt $attempt */
        $attempt = $answer->attempt()->with(['answers', 'challenge.questions', 'user'])->firstOrFail();

        $allGraded = $attempt->answers->every(fn (UserChallengeAnswer $ans) => $ans->points_awarded !== null);

        if ($allGraded && $attempt->status !== ChallengeAttemptStatus::Graded) {
            $totalEarned = (int) $attempt->answers->sum('points_awarded');
            $totalMax = (int) $attempt->challenge->questions->sum('max_points');
            $scorePercent = $totalMax > 0 ? round(($totalEarned / $totalMax) * 100, 2) : 0.0;
            $coins = $this->calculateCoinsForScore($scorePercent);

            $attempt->status = ChallengeAttemptStatus::Graded;
            $attempt->total_score_percent = $scorePercent;
            $attempt->reward_coins_awarded = $coins;
            $attempt->save();

            if ($coins > 0) {
                $this->coinService->award($attempt->user, $coins, CoinTransactionType::EarnedChallenge, $attempt->challenge);
            }
        }

        return [
            'answer_id' => $answer->id,
            'points_awarded' => $pointsAwarded,
            'attempt_status' => $attempt->status->value,
            'total_score_percent' => $attempt->total_score_percent,
            'reward_coins_awarded' => $attempt->reward_coins_awarded,
        ];
    }

    /**
     * Calculate coin rewards based on score percentage.
     */
    public function calculateCoinsForScore(float $scorePercent): int
    {
        if ($scorePercent >= 95.0) {
            return 100;
        }

        if ($scorePercent >= 70.0) {
            return 50;
        }

        return 0;
    }

    /**
     * Get published challenges for student.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPublishedChallengesForUser(User $user): array
    {
        $now = now();

        $query = WeeklyChallenge::where('status', ChallengeStatus::Published)
            ->where('week_start_date', '<=', $now)
            ->where('week_end_date', '>=', $now)
            ->with(['subject', 'attempts' => fn ($q) => $q->where('user_id', $user->id)]);

        if ($user->exam_system) {
            $userSubsystem = $user->exam_system instanceof \BackedEnum ? $user->exam_system->value : (string) $user->exam_system;
            $query->where(function ($q) use ($userSubsystem) {
                $q->whereNull('exam_subsystem')
                    ->orWhere('exam_subsystem', 'general')
                    ->orWhere('exam_subsystem', $userSubsystem);
            });
        }

        if ($user->level) {
            $userLevel = $user->level instanceof \BackedEnum ? $user->level->value : (string) $user->level;
            $query->where(function ($q) use ($userLevel) {
                $q->whereNull('level')
                    ->orWhere('level', $userLevel);
            });
        }

        $challenges = $query->get();

        return $challenges->map(function (WeeklyChallenge $challenge) use ($user): array {
            /** @var UserChallengeAttempt|null $attempt */
            $attempt = $challenge->attempts->firstWhere('user_id', $user->id);

            return [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'subject' => [
                    'id' => $challenge->subject_id,
                    'name' => $challenge->subject->name,
                ],
                'exam_subsystem' => $challenge->exam_subsystem,
                'level' => $challenge->level,
                'time_limit_minutes' => $challenge->time_limit_minutes,
                'week_start_date' => $challenge->week_start_date->toIso8601String(),
                'week_end_date' => $challenge->week_end_date->toIso8601String(),
                'has_attempted' => $attempt !== null,
                'attempt_status' => $attempt?->status->value,
                'attempt_id' => $attempt?->id,
            ];
        })->values()->all();
    }

    /**
     * Format questions for student (strictly stripping correct_choice).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSanitizedQuestions(WeeklyChallenge $challenge): array
    {
        return $challenge->questions()
            ->orderBy('order', 'asc')
            ->get()
            ->map(function (WeeklyChallengeQuestion $question): array {
                return [
                    'id' => $question->id,
                    'type' => $question->type->value,
                    'question_text' => $question->question_text,
                    'options' => $question->options,
                    'max_points' => $question->max_points,
                    'order' => $question->order,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get challenge attempt result for student.
     *
     * @return array<string, mixed>
     */
    public function getAttemptResult(User $user, WeeklyChallenge $challenge): array
    {
        /** @var UserChallengeAttempt|null $attempt */
        $attempt = UserChallengeAttempt::where('user_id', $user->id)
            ->where('weekly_challenge_id', $challenge->id)
            ->with(['answers.question'])
            ->first();

        if (! $attempt) {
            return [
                'has_attempted' => false,
                'status' => null,
                'attempt' => null,
            ];
        }

        $answers = null;
        if ($attempt->status === ChallengeAttemptStatus::Graded) {
            $answers = $attempt->answers->map(function (UserChallengeAnswer $ans): array {
                $data = [
                    'question_id' => $ans->question_id,
                    'type' => $ans->question->type->value,
                    'question_text' => $ans->question->question_text,
                    'selected_choice' => $ans->selected_choice,
                    'answer_text' => $ans->answer_text,
                    'points_awarded' => $ans->points_awarded,
                    'max_points' => $ans->question->max_points,
                ];

                if ($ans->question->type === ChallengeQuestionType::Structural && $ans->suggested_justification !== null) {
                    $data['feedback'] = $ans->suggested_justification;
                }

                return $data;
            })->values()->all();
        }

        return [
            'has_attempted' => true,
            'status' => $attempt->status->value,
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'status' => $attempt->status->value,
                'total_score_percent' => $attempt->total_score_percent,
                'reward_coins_awarded' => $attempt->reward_coins_awarded,
                'answers' => $answers,
            ],
        ];
    }
}
