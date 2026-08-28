<?php

namespace App\Services;

use App\Enums\AiTutorMessageRole;
use App\Models\AiTutorConversation;
use App\Models\AiTutorMessage;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TutorService
{
    public function __construct(
        protected AccessControlService $accessControlService
    ) {}

    /**
     * Start a new conversation or retrieve an existing per-chapter thread.
     */
    public function startOrGetConversation(User $user, ?int $chapterId = null): AiTutorConversation
    {
        if ($chapterId !== null) {
            /** @var Chapter $chapter */
            $chapter = Chapter::with('subject')->findOrFail($chapterId);

            /** @var AiTutorConversation|null $existing */
            $existing = AiTutorConversation::where('user_id', $user->id)
                ->where('chapter_id', $chapter->id)
                ->latest('last_message_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var AiTutorConversation $newConv */
            $newConv = AiTutorConversation::create([
                'user_id' => $user->id,
                'chapter_id' => $chapter->id,
                'title' => "{$chapter->subject->name}: {$chapter->title}",
                'last_message_at' => now(),
            ]);

            return $newConv;
        }

        /** @var AiTutorConversation $generalConv */
        $generalConv = AiTutorConversation::create([
            'user_id' => $user->id,
            'chapter_id' => null,
            'title' => 'General Discussion',
            'last_message_at' => now(),
        ]);

        return $generalConv;
    }

    /**
     * Send a message in a conversation and retrieve Lumani's AI reply.
     *
     * @return array{
     *     conversation_id: int,
     *     user_message: array{id: int, role: string, content: string, created_at: string|null},
     *     assistant_message: array{id: int, role: string, content: string, created_at: string|null}
     * }
     */
    public function sendMessage(User $user, AiTutorConversation $conversation, string $messageText): array
    {
        // 1. Subscription Check (free_mode bypasses)
        if (! $this->accessControlService->hasActiveSubscription($user)) {
            abort(403, 'An active subscription is required to chat with AI Tutor Lumani.');
        }

        // 2. Ownership Check
        if ($conversation->user_id !== $user->id) {
            abort(403, 'Unauthorized conversation access.');
        }

        $trimmedText = trim($messageText);
        if ($trimmedText === '') {
            throw ValidationException::withMessages([
                'message' => 'Message content cannot be empty.',
            ]);
        }

        // 3. Store User Message
        /** @var AiTutorMessage $userMsg */
        $userMsg = AiTutorMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiTutorMessageRole::User,
            'content' => $trimmedText,
        ]);

        if (empty($conversation->title) || $conversation->title === 'General Discussion') {
            $conversation->title = Str::limit($trimmedText, 45);
        }

        // 4. Query Gemini with bounding context (last 15 messages)
        $reply = $this->queryGeminiForReply($conversation);

        if ($reply === null || trim($reply) === '') {
            throw ValidationException::withMessages([
                'gemini' => 'Lumani tutor is temporarily unavailable. Please try again.',
            ]);
        }

        // 5. Store Assistant Message & update last_message_at
        /** @var AiTutorMessage $assistantMsg */
        $assistantMsg = AiTutorMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiTutorMessageRole::Assistant,
            'content' => trim($reply),
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        return [
            'conversation_id' => $conversation->id,
            'user_message' => [
                'id' => $userMsg->id,
                'role' => $userMsg->role->value,
                'content' => $userMsg->content,
                'created_at' => $userMsg->created_at?->toIso8601String(),
            ],
            'assistant_message' => [
                'id' => $assistantMsg->id,
                'role' => $assistantMsg->role->value,
                'content' => $assistantMsg->content,
                'created_at' => $assistantMsg->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Query Gemini API using conversation context and bound history (last 15 messages).
     */
    protected function queryGeminiForReply(AiTutorConversation $conversation): ?string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-3.5-flash');

        if (empty($apiKey)) {
            Log::warning('TutorService: GEMINI_API_KEY is not configured.');

            return null;
        }

        // Build Persona & Chapter Context
        $systemPrompt = 'You are Lumani, a friendly, encouraging AI tutor helping a Cameroonian secondary student prepare for GCE/Baccalauréat exams. Explain concepts clearly, use the Socratic method when helpful, keep answers focused and exam-relevant.';

        if ($conversation->chapter_id !== null) {
            $chapter = $conversation->chapter()->with('subject')->first();
            if ($chapter) {
                $systemPrompt .= " Context: The student is studying the chapter '{$chapter->title}' in subject '{$chapter->subject->name}'.";
            }
        }

        // Retrieve the last 15 messages in chronological order
        /** @var Collection<int, AiTutorMessage> $recentMessages */
        $recentMessages = AiTutorMessage::where('conversation_id', $conversation->id)
            ->latest('id')
            ->take(15)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        foreach ($recentMessages as $msg) {
            $contents[] = [
                'role' => $msg->role === AiTutorMessageRole::User ? 'user' : 'model',
                'parts' => [
                    ['text' => $msg->content],
                ],
            ];
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(15)
                ->asJson()
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemPrompt],
                        ],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('TutorService: Gemini API returned error response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $responseData = $response->json();
            $replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            return is_string($replyText) ? $replyText : null;
        } catch (Throwable $e) {
            Log::warning('TutorService: Exception calling Gemini API', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * List conversations for the student.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserConversations(User $user): array
    {
        return AiTutorConversation::where('user_id', $user->id)
            ->with(['chapter.subject'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (AiTutorConversation $conv): array {
                return [
                    'id' => $conv->id,
                    'chapter_id' => $conv->chapter_id,
                    'chapter_title' => $conv->chapter?->title,
                    'subject_name' => $conv->chapter?->subject?->name,
                    'title' => $conv->title,
                    'last_message_at' => $conv->last_message_at->toIso8601String(),
                    'created_at' => $conv->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get full message history for a conversation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConversationMessages(User $user, AiTutorConversation $conversation): array
    {
        if ($conversation->user_id !== $user->id) {
            abort(403, 'Unauthorized conversation access.');
        }

        return $conversation->messages()
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (AiTutorMessage $msg): array {
                return [
                    'id' => $msg->id,
                    'role' => $msg->role->value,
                    'content' => $msg->content,
                    'created_at' => $msg->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
