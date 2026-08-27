<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiTutorConversation;
use App\Services\TutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiTutorController extends Controller
{
    /**
     * List the student's conversations, most recent first.
     */
    public function index(Request $request, TutorService $service): JsonResponse
    {
        $conversations = $service->getUserConversations($request->user());

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Start a new conversation or retrieve existing per-chapter thread.
     */
    public function store(Request $request, TutorService $service): JsonResponse
    {
        $validated = $request->validate([
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
        ]);

        $conversation = $service->startOrGetConversation(
            $request->user(),
            isset($validated['chapter_id']) ? (int) $validated['chapter_id'] : null
        );

        $conversation->loadMissing(['chapter.subject']);

        return response()->json([
            'message' => 'Conversation initialized successfully.',
            'conversation' => [
                'id' => $conversation->id,
                'chapter_id' => $conversation->chapter_id,
                'chapter_title' => $conversation->chapter?->title,
                'subject_name' => $conversation->chapter?->subject?->name,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at->toIso8601String(),
                'created_at' => $conversation->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get full message history for a conversation.
     */
    public function messages(Request $request, int $id, TutorService $service): JsonResponse
    {
        /** @var AiTutorConversation $conversation */
        $conversation = AiTutorConversation::findOrFail($id);

        $messages = $service->getConversationMessages($request->user(), $conversation);

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message to Lumani AI tutor and retrieve response.
     */
    public function sendMessage(Request $request, int $id, TutorService $service): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        /** @var AiTutorConversation $conversation */
        $conversation = AiTutorConversation::findOrFail($id);

        $result = $service->sendMessage($request->user(), $conversation, $validated['message']);

        return response()->json(array_merge([
            'message' => 'Reply received from Lumani.',
        ], $result));
    }
}
