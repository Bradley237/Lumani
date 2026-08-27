<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterProgressController extends Controller
{
    /**
     * Touch a chapter: marks it as in_progress and updates last_accessed_at.
     */
    public function touch(Request $request, int $id, QuizService $quizService): JsonResponse
    {
        /** @var Chapter $chapter */
        $chapter = Chapter::findOrFail($id);

        $progress = $quizService->touchChapter($request->user(), $chapter);

        return response()->json([
            'message' => 'Chapter progress updated.',
            'progress' => [
                'chapter_id' => $progress->chapter_id,
                'state' => $progress->state->value,
                'last_accessed_at' => $progress->last_accessed_at?->toIso8601String(),
                'completed_at' => $progress->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get overall chapter progress across all subjects and chapters.
     */
    public function progress(Request $request, QuizService $quizService): JsonResponse
    {
        $data = $quizService->getStudentProgress($request->user());

        return response()->json($data);
    }
}
