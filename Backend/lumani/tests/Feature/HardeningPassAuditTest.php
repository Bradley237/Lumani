<?php

use App\Models\AiTutorConversation;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\ExamSession;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use App\Services\AccessControlService;
use Database\Seeders\FreeChapterSeeder;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        FreeChapterSeeder::class,
        MissionAndRewardSeeder::class,
    ]);
});

test('free mode globally bypasses coin unlocks and subscription gates', function () {
    $setting = AppSetting::firstOrCreate(['id' => 1]);
    $setting->free_mode_enabled = true;
    $setting->save();

    $student = User::factory()->student()->create(['coin_balance' => 0]);
    $service = app(AccessControlService::class);

    $subject = Subject::factory()->create();
    $lockedChapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'order' => 10,
        'is_free' => false,
        'coin_price' => 100,
    ]);

    $pastPaper = PastPaper::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'solution_coin_price' => 75,
    ]);

    expect($service->canAccessChapter($student, $lockedChapter))->toBeTrue();
    expect($service->canAccessPastPaper($student, $pastPaper))->toBeTrue();
    expect($service->canAccessPastPaperSolution($student, $pastPaper))->toBeTrue();
    expect($service->hasActiveSubscription($student))->toBeTrue();
});

test('correct_choice is never leaked in quiz, exam session, or weekly challenge student fetches', function () {
    $student = User::factory()->student()->create();

    // 1. Quiz questions
    $chapter = Chapter::factory()->create(['is_free' => true]);
    $quiz = Quiz::factory()->create(['chapter_id' => $chapter->id]);
    Question::factory()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'What is the speed of light?',
        'correct_choice' => 'C',
    ]);

    $quizResponse = $this->actingAs($student)->getJson("/api/quizzes/{$quiz->id}");
    $quizResponse->assertOk();
    $quizQuestions = $quizResponse->json('questions');
    foreach ($quizQuestions as $q) {
        expect($q)->not->toHaveKey('correct_choice');
        expect($q)->not->toHaveKey('explanation');
    }

    // 2. Exam session questions
    $pastPaper = PastPaper::factory()->create();
    PastPaperQuestion::factory()->create([
        'past_paper_id' => $pastPaper->id,
        'correct_choice' => 'B',
        'marking_scheme' => 'Secret marking rubric',
    ]);

    $setting = AppSetting::firstOrCreate(['id' => 1]);
    $setting->free_mode_enabled = true;
    $setting->save();

    $examStartResponse = $this->actingAs($student)->postJson("/api/past-papers/{$pastPaper->id}/exam-session/start");
    $examStartResponse->assertCreated();
    $examQuestions = $examStartResponse->json('questions');
    foreach ($examQuestions as $q) {
        expect($q)->not->toHaveKey('correct_choice');
        expect($q)->not->toHaveKey('marking_scheme');
    }

    // 3. Weekly challenge questions
    $challenge = WeeklyChallenge::factory()->create([
        'week_start_date' => now()->startOfWeek(),
        'week_end_date' => now()->endOfWeek(),
    ]);
    WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'correct_choice' => 'A',
    ]);

    $challengeStart = $this->actingAs($student)->postJson("/api/challenges/{$challenge->id}/start");
    $challengeStart->assertCreated();
    $challengeQuestions = $challengeStart->json('challenge.questions');
    expect($challengeQuestions)->not->toBeEmpty();
    foreach ($challengeQuestions as $q) {
        expect($q)->not->toHaveKey('correct_choice');
    }
});

test('student cannot access another student conversations or exam sessions', function () {
    $studentA = User::factory()->student()->create();
    $studentB = User::factory()->student()->create();

    $conversation = AiTutorConversation::create([
        'user_id' => $studentA->id,
        'title' => 'Secret Conversation A',
        'last_message_at' => now(),
    ]);

    $examSession = ExamSession::create([
        'user_id' => $studentA->id,
        'past_paper_id' => PastPaper::factory()->create()->id,
        'max_allowed_minutes' => 60,
        'selected_minutes' => 60,
        'started_at' => now(),
        'status' => 'in_progress',
    ]);

    // Student B attempts to access Student A's tutor conversation messages
    $responseMsg = $this->actingAs($studentB)->getJson("/api/tutor/conversations/{$conversation->id}/messages");
    $responseMsg->assertForbidden();

    // Student B attempts to access Student A's exam session result
    $responseExam = $this->actingAs($studentB)->getJson("/api/exam-sessions/{$examSession->id}/result");
    $responseExam->assertUnprocessable();
});

test('rate limiting blocks excessive authentication attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/login', [
            'email' => "wrong_{$i}@example.com",
            'password' => 'WrongPassword!',
        ]);
    }

    $blockedResponse = $this->postJson('/api/login', [
        'email' => 'wrong_11@example.com',
        'password' => 'WrongPassword!',
    ]);

    $blockedResponse->assertStatus(429);
});

test('input validation properly rejects negative and malformed parameters', function () {
    $student = User::factory()->student()->create();

    // Negative minutes on exam session start
    $pastPaper = PastPaper::factory()->create();
    $this->actingAs($student)->postJson("/api/past-papers/{$pastPaper->id}/exam-session/start", [
        'requested_minutes' => -10,
    ])->assertUnprocessable();

    // Invalid subscription tier
    $this->actingAs($student)->postJson('/api/subscriptions/purchase', [
        'tier' => 'tier_unlimited_free',
    ])->assertUnprocessable();

    // Invalid days on revision plan generate
    $this->actingAs($student)->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 120,
        'available_days' => [7, -1, 99],
    ])->assertUnprocessable();
});
