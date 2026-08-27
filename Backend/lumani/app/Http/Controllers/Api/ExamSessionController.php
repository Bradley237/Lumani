<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\PastPaper;
use App\Services\ExamSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    /**
     * Start a timed exam session for a past paper.
     */
    public function start(Request $request, int $id, ExamSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'requested_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $result = $service->startSession(
            $request->user(),
            $pastPaper,
            isset($validated['requested_minutes']) ? (int) $validated['requested_minutes'] : null
        );

        return response()->json(array_merge([
            'message' => 'Exam session started successfully.',
        ], $result), 201);
    }

    /**
     * Submit an active exam session with student answers.
     */
    public function submit(Request $request, int $id, ExamSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_choice' => ['nullable', 'string'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        /** @var ExamSession $session */
        $session = ExamSession::with('pastPaper.questions')->findOrFail($id);

        $result = $service->submitSession($request->user(), $session, $validated['answers']);

        return response()->json($result);
    }

    /**
     * Get the result of an exam session.
     */
    public function result(Request $request, int $id, ExamSessionService $service): JsonResponse
    {
        /** @var ExamSession $session */
        $session = ExamSession::findOrFail($id);

        $result = $service->getSessionResult($request->user(), $session);

        return response()->json([
            'status' => $result['status'],
            'result' => $result,
        ]);
    }
}
