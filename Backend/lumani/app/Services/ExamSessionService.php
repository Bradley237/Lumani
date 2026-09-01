<?php

namespace App\Services;

use App\Enums\ExamSessionStatus;
use App\Enums\PastPaperQuestionType;
use App\Models\BusinessSetting;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ExamSessionService
{
    public function __construct(
        protected AccessControlService $accessControlService,
        protected GradingAssistantService $gradingAssistantService
    ) {}

    /**
     * Start a timed exam session for a past paper.
     *
     * @return array{
     *     session: array<string, mixed>,
     *     questions: array<int, array{id: int, type: string, question_text: string, options: array<string, string>|null, max_points: int, order: int}>
     * }
     */
    public function startSession(User $user, PastPaper $pastPaper, ?int $requestedMinutes = null): array
    {
        // 1. Subscription Check (free_mode bypasses)
        if (! $this->accessControlService->hasActiveSubscription($user)) {
            abort(403, 'An active subscription is required to access timed exam sessions.');
        }

        // 2. Compute max allowed minutes based on composition
        $questions = $pastPaper->questions()->orderBy('order', 'asc')->get();

        $hasMcq = $questions->contains(fn (PastPaperQuestion $q) => $q->type === PastPaperQuestionType::Mcq);
        $hasStructural = $questions->contains(fn (PastPaperQuestion $q) => $q->type === PastPaperQuestionType::Structural);

        if ($hasMcq && ! $hasStructural) {
            $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_mcq_minutes', 90);
        } elseif (! $hasMcq && $hasStructural) {
            $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_structural_minutes', 180);
        } else {
            // Mixed (or empty) defaults to mixed setting
            $maxAllowedMinutes = (int) BusinessSetting::get('exam_time_cap_mixed_minutes', 240);
        }

        // 3. Validate requested minutes
        if ($requestedMinutes !== null) {
            if ($requestedMinutes <= 0 || $requestedMinutes > $maxAllowedMinutes) {
                throw ValidationException::withMessages([
                    'requested_minutes' => "Requested duration must be between 1 and {$maxAllowedMinutes} minutes.",
                ]);
            }
            $selectedMinutes = $requestedMinutes;
        } else {
            $selectedMinutes = $maxAllowedMinutes;
        }

        // 4. Create ExamSession record
        /** @var ExamSession $session */
        $session = ExamSession::create([
            'user_id' => $user->id,
            'past_paper_id' => $pastPaper->id,
            'max_allowed_minutes' => $maxAllowedMinutes,
            'selected_minutes' => $selectedMinutes,
            'started_at' => now(),
            'status' => ExamSessionStatus::InProgress,
        ]);

        // 5. Questions sanitized (never leak correct_choice or marking_scheme)
        $sanitizedQuestions = $questions->map(function (PastPaperQuestion $q): array {
            return [
                'id' => $q->id,
                'type' => $q->type->value,
                'question_text' => $q->question_text,
                'options' => $q->options,
                'max_points' => $q->max_points,
                'order' => $q->order,
            ];
        })->values()->all();

        return [
            'session' => [
                'id' => $session->id,
                'past_paper_id' => $session->past_paper_id,
                'max_allowed_minutes' => $session->max_allowed_minutes,
                'selected_minutes' => $session->selected_minutes,
                'started_at' => $session->started_at->toIso8601String(),
                'status' => $session->status->value,
            ],
            'questions' => $sanitizedQuestions,
        ];
    }

    /**
     * Submit an exam session with answers.
     *
     * @param  array<int, array{question_id: int, selected_choice?: string|null, answer_text?: string|null}>  $answers
     * @return array{
     *     status: string,
     *     message: string,
     *     session_id: int,
     *     total_score_percent?: float|null
     * }
     */
    public function submitSession(User $user, ExamSession $session, array $answers): array
    {
        if ($session->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'session' => 'Unauthorized exam session submission.',
            ]);
        }

        if ($session->status !== ExamSessionStatus::InProgress) {
            throw ValidationException::withMessages([
                'session' => 'This exam session has already been submitted.',
            ]);
        }

        $startedAt = Carbon::parse($session->started_at);
        $deadline = $startedAt->copy()->addMinutes($session->selected_minutes)->addSeconds(60);

        if (now()->gt($deadline)) {
            throw ValidationException::withMessages([
                'time_limit' => 'Time limit exceeded for this exam session.',
            ]);
        }

        return DB::transaction(function () use ($session, $answers): array {
            $pastPaper = $session->pastPaper;
            $questions = $pastPaper->questions()->get();
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

                if ($question->type === PastPaperQuestionType::Mcq) {
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

                ExamSessionAnswer::updateOrCreate(
                    [
                        'exam_session_id' => $session->id,
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

                $session->status = ExamSessionStatus::Graded;
                $session->submitted_at = now();
                $session->total_score_percent = $scorePercent;
                $session->save();

                return [
                    'status' => $session->status->value,
                    'message' => 'Exam session submitted and graded successfully.',
                    'session_id' => $session->id,
                    'total_score_percent' => $scorePercent,
                ];
            }

            $session->status = ExamSessionStatus::Submitted;
            $session->submitted_at = now();
            $session->total_score_percent = null;
            $session->save();

            return [
                'status' => $session->status->value,
                'message' => 'Exam session submitted successfully. Results will be available after teacher grading.',
                'session_id' => $session->id,
                'total_score_percent' => null,
            ];
        });
    }

    /**
     * Grade a structural question in an exam session.
     *
     * @return array{
     *     answer_id: int,
     *     points_awarded: int,
     *     session_status: string,
     *     total_score_percent?: float|null
     * }
     */
    public function gradeStructuralAnswer(User $admin, ExamSessionAnswer $answer, int $pointsAwarded): array
    {
        $question = $answer->question;

        if ($pointsAwarded < 0 || $pointsAwarded > $question->max_points) {
            throw new InvalidArgumentException("Points awarded must be between 0 and {$question->max_points}.");
        }

        $answer->points_awarded = $pointsAwarded;
        $answer->save();

        /** @var ExamSession $session */
        $session = $answer->session()->with(['answers', 'pastPaper.questions', 'user'])->firstOrFail();

        $allGraded = $session->answers->every(fn (ExamSessionAnswer $ans) => $ans->points_awarded !== null);

        if ($allGraded && $session->status !== ExamSessionStatus::Graded) {
            $totalEarned = (int) $session->answers->sum('points_awarded');
            $totalMax = (int) $session->pastPaper->questions->sum('max_points');
            $scorePercent = $totalMax > 0 ? round(($totalEarned / $totalMax) * 100, 2) : 0.0;

            $session->status = ExamSessionStatus::Graded;
            $session->total_score_percent = $scorePercent;
            $session->save();
        }

        return [
            'answer_id' => $answer->id,
            'points_awarded' => $pointsAwarded,
            'session_status' => $session->status->value,
            'total_score_percent' => $session->total_score_percent,
        ];
    }

    /**
     * Get result details for an exam session.
     *
     * @return array<string, mixed>
     */
    public function getSessionResult(User $user, ExamSession $session): array
    {
        if ($session->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'session' => 'Unauthorized exam session access.',
            ]);
        }

        $session->loadMissing(['answers.question', 'pastPaper.subject']);

        $answers = null;
        if ($session->status === ExamSessionStatus::Graded) {
            $answers = $session->answers->map(function (ExamSessionAnswer $ans): array {
                $data = [
                    'question_id' => $ans->question_id,
                    'type' => $ans->question->type->value,
                    'question_text' => $ans->question->question_text,
                    'selected_choice' => $ans->selected_choice,
                    'answer_text' => $ans->answer_text,
                    'points_awarded' => $ans->points_awarded,
                    'max_points' => $ans->question->max_points,
                ];

                if ($ans->question->type === PastPaperQuestionType::Structural && $ans->suggested_justification !== null) {
                    $data['feedback'] = $ans->suggested_justification;
                }

                return $data;
            })->values()->all();
        }

        return [
            'id' => $session->id,
            'past_paper_id' => $session->past_paper_id,
            'past_paper_title' => $session->pastPaper->title,
            'subject_name' => $session->pastPaper->subject->name,
            'selected_minutes' => $session->selected_minutes,
            'started_at' => $session->started_at->toIso8601String(),
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'status' => $session->status->value,
            'total_score_percent' => $session->total_score_percent,
            'answers' => $answers,
        ];
    }
}
