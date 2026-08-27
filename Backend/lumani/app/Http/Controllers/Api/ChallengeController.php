<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallenge;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function __construct(
        protected ChallengeService $challengeService
    ) {}

    /**
     * List all published weekly challenges available for the authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $challenges = $this->challengeService->getPublishedChallengesForUser($user);

        return response()->json([
            'challenges' => $challenges,
        ]);
    }

    /**
     * Start a challenge attempt and return questions without correct choice.
     */
    public function start(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WeeklyChallenge $challenge */
        $challenge = WeeklyChallenge::findOrFail($id);

        $attempt = $this->challengeService->startAttempt($user, $challenge);
        $questions = $this->challengeService->getSanitizedQuestions($challenge);

        return response()->json([
            'message' => 'Weekly challenge started.',
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
                'status' => $attempt->status->value,
            ],
            'challenge' => [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'time_limit_minutes' => $challenge->time_limit_minutes,
                'questions' => $questions,
            ],
        ], 201);
    }

    /**
     * Submit answers for a challenge attempt.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WeeklyChallenge $challenge */
        $challenge = WeeklyChallenge::findOrFail($id);

        /** @var UserChallengeAttempt $attempt */
        $attempt = UserChallengeAttempt::where('user_id', $user->id)
            ->where('weekly_challenge_id', $challenge->id)
            ->firstOrFail();

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:weekly_challenge_questions,id'],
            'answers.*.selected_choice' => ['nullable', 'string'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        $result = $this->challengeService->submitAttempt($user, $attempt, $validated['answers']);

        return response()->json($result);
    }

    /**
     * Get the result/status of an attempt.
     */
    public function result(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var WeeklyChallenge $challenge */
        $challenge = WeeklyChallenge::findOrFail($id);

        $result = $this->challengeService->getAttemptResult($user, $challenge);

        return response()->json($result);
    }
}
