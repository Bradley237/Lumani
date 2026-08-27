<?php

use App\Enums\AiTutorMessageRole;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\AiTutorConversation;
use App\Models\AiTutorMessage;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();
    Config::set('services.gemini.api_key', 'test-gemini-api-key');
});

test('cannot send tutor message without an active subscription', function () {
    $user = User::factory()->student()->create();
    $conversation = AiTutorConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tutor/conversations/{$conversation->id}/messages", [
        'message' => 'Hello Lumani, can you explain quadratic equations?',
    ]);

    $response->assertStatus(403);
});

test('free mode allows sending message without an active subscription', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    $user = User::factory()->student()->create();
    $conversation = AiTutorConversation::factory()->create(['user_id' => $user->id]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Quadratic equations have the form ax^2 + bx + c = 0.'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tutor/conversations/{$conversation->id}/messages", [
        'message' => 'Hello Lumani, can you explain quadratic equations?',
    ]);

    $response->assertOk()
        ->assertJsonPath('user_message.content', 'Hello Lumani, can you explain quadratic equations?')
        ->assertJsonPath('assistant_message.content', 'Quadratic equations have the form ax^2 + bx + c = 0.');
});

test('starting a conversation for the same chapter twice reuses the existing thread', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $chapter = Chapter::factory()->create(['subject_id' => $subject->id, 'title' => 'Thermodynamics']);

    // 1. First initialization
    $resp1 = $this->actingAs($user, 'sanctum')->postJson('/api/tutor/conversations', [
        'chapter_id' => $chapter->id,
    ]);
    $resp1->assertStatus(201);
    $convId1 = $resp1->json('conversation.id');

    // 2. Second initialization with the same chapter
    $resp2 = $this->actingAs($user, 'sanctum')->postJson('/api/tutor/conversations', [
        'chapter_id' => $chapter->id,
    ]);
    $resp2->assertStatus(201);
    $convId2 = $resp2->json('conversation.id');

    expect($convId2)->toBe($convId1);
    expect(AiTutorConversation::where('user_id', $user->id)->count())->toBe(1);
});

test('starting without a chapter always creates a new general conversation', function () {
    $user = User::factory()->student()->create();

    $resp1 = $this->actingAs($user, 'sanctum')->postJson('/api/tutor/conversations');
    $resp1->assertStatus(201);
    $convId1 = $resp1->json('conversation.id');

    $resp2 = $this->actingAs($user, 'sanctum')->postJson('/api/tutor/conversations');
    $resp2->assertStatus(201);
    $convId2 = $resp2->json('conversation.id');

    expect($convId2)->not->toBe($convId1);
    expect(AiTutorConversation::where('user_id', $user->id)->count())->toBe(2);
});

test('only the last 15 messages are sent as context to Gemini when conversation is long', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'tier' => SubscriptionTier::Tier2000,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $conversation = AiTutorConversation::factory()->create(['user_id' => $user->id]);

    // Create 20 previous messages in this conversation (10 user, 10 assistant)
    for ($i = 1; $i <= 20; $i++) {
        $padded = sprintf('%02d', $i);
        AiTutorMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => $i % 2 === 1 ? AiTutorMessageRole::User : AiTutorMessageRole::Assistant,
            'content' => "Historic message number {$padded}",
        ]);
    }

    Http::fake([
        'generativelanguage.googleapis.com/*' => function ($request) {
            $data = json_decode($request->body(), true);
            $contents = $data['contents'] ?? [];

            // Exactly 15 messages sent to Gemini (including the newly added 21st user message)
            expect(count($contents))->toBe(15);

            // Verify oldest messages (01 through 06) are pruned and not sent
            $allText = json_encode($contents);
            expect($allText)->not->toContain('Historic message number 01');
            expect($allText)->not->toContain('Historic message number 05');
            expect($allText)->toContain('Historic message number 20');
            expect($allText)->toContain('New test message 21');

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Lumani tutor answer 21.'],
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tutor/conversations/{$conversation->id}/messages", [
        'message' => 'New test message 21',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'Lumani tutor answer 21.');
});

test('a failed Gemini call does not persist a broken assistant message', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $conversation = AiTutorConversation::factory()->create(['user_id' => $user->id]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'API quota exhausted'], 503),
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tutor/conversations/{$conversation->id}/messages", [
        'message' => 'What is Newton second law?',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['gemini']);

    // User message was stored, but no assistant message was saved
    expect(AiTutorMessage::where('conversation_id', $conversation->id)->where('role', AiTutorMessageRole::Assistant->value)->count())->toBe(0);
});

test('conversation list only returns the requesting user conversations', function () {
    $user1 = User::factory()->student()->create();
    $user2 = User::factory()->student()->create();

    $conv1 = AiTutorConversation::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Biology Chat']);
    $conv2 = AiTutorConversation::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Chemistry Chat']);

    $response = $this->actingAs($user1, 'sanctum')->getJson('/api/tutor/conversations');

    $response->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conv1->id)
        ->assertJsonPath('conversations.0.title', 'User 1 Biology Chat');
});

test('can retrieve full message history for user conversation', function () {
    $user = User::factory()->student()->create();
    $conversation = AiTutorConversation::factory()->create(['user_id' => $user->id]);

    $msg1 = AiTutorMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => AiTutorMessageRole::User,
        'content' => 'First user question',
    ]);
    $msg2 = AiTutorMessage::factory()->assistant()->create([
        'conversation_id' => $conversation->id,
        'content' => 'First Lumani answer',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/tutor/conversations/{$conversation->id}/messages");

    $response->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.content', 'First user question')
        ->assertJsonPath('messages.1.content', 'First Lumani answer');
});
