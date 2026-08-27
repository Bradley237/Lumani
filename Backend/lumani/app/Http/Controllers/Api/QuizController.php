<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Fetch quiz details and questions sanitized for student (without correct choices).
     */
    public function show(Request $request, int $id, QuizService $quizService): JsonResponse
    {
        /** @var Quiz $quiz */
        $quiz = Quiz::with('chapter')->findOrFail($id);

        $data = $quizService->getQuizForStudent($request->user(), $quiz);

        return response()->json($data);
    }

    /**
     * Submit a quiz attempt with student answers, grade them, award XP and update progress.
     */
    public function submit(Request $request, int $id, QuizService $quizService): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_choice' => ['nullable', 'string'],
        ]);

        /** @var Quiz $quiz */
        $quiz = Quiz::with('chapter')->findOrFail($id);

        $result = $quizService->submitQuiz($request->user(), $quiz, $validated['answers']);

        return response()->json(array_merge([
            'message' => 'Quiz submitted successfully.',
        ], $result));
    }
}
