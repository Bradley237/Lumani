<?php

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeQuestionType;
use App\Enums\ChallengeStatus;
use App\Models\CoinTransaction;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserChallengeAnswer;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use App\Services\ChallengeService;
use Carbon\Carbon;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('student can list published challenges and sees attempt status', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'exam_subsystem' => null,
        'level' => null,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/challenges');

    $response->assertOk()
        ->assertJsonStructure([
            'challenges' => [
                '*' => [
                    'id',
                    'title',
                    'subject',
                    'time_limit_minutes',
                    'week_start_date',
                    'week_end_date',
                    'has_attempted',
                    'attempt_status',
                ],
            ],
        ]);

    expect($response->json('challenges.0.has_attempted'))->toBeFalse();
});

test('starting a challenge returns questions WITHOUT leaking correct_choice', function () {
    $user = User::factory()->student()->create();
    $challenge = WeeklyChallenge::factory()->create([
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
        'time_limit_minutes' => 20,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'type' => ChallengeQuestionType::Mcq,
        'question_text' => 'What is 2 + 2?',
        'options' => ['A' => '3', 'B' => '4', 'C' => '5'],
        'correct_choice' => 'B',
        'max_points' => 10,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start");

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'attempt' => ['id', 'started_at', 'status'],
            'challenge' => [
                'id',
                'title',
                'time_limit_minutes',
                'questions' => [
                    '*' => ['id', 'type', 'question_text', 'options', 'max_points'],
                ],
            ],
        ]);

    // Ensure correct_choice is NOT present in any returned question
    $questions = $response->json('challenge.questions');
    foreach ($questions as $q) {
        expect(array_key_exists('correct_choice', $q))->toBeFalse();
    }
});

test('student cannot start a second attempt on the same challenge', function () {
    $user = User::factory()->student()->create();
    $challenge = WeeklyChallenge::factory()->create([
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    // First attempt
    $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);

    // Second attempt attempt rejected
    $response = $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start");
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['challenge']);
});

test('cannot submit challenge after time limit expires', function () {
    $user = User::factory()->student()->create();
    $challenge = WeeklyChallenge::factory()->create([
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
        'time_limit_minutes' => 15,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'type' => ChallengeQuestionType::Mcq,
        'correct_choice' => 'A',
    ]);

    Carbon::setTestNow(now());
    $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);

    // Fast-forward time past time limit (15 min limit + 60s grace = 16 min) -> advance by 20 minutes
    Carbon::setTestNow(now()->addMinutes(20));

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['time_limit']);
});

test('mcq only challenge grades and awards coins instantly on submit based on score tiers', function () {
    $user1 = User::factory()->student()->create(['coin_balance' => 0]);
    $user2 = User::factory()->student()->create(['coin_balance' => 0]);
    $user3 = User::factory()->student()->create(['coin_balance' => 0]);

    $challenge = WeeklyChallenge::factory()->create([
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
        'time_limit_minutes' => 30,
    ]);

    // Create 10 MCQ questions (10 pts each = 100 max points)
    $questions = [];
    for ($i = 1; $i <= 10; $i++) {
        $questions[] = WeeklyChallengeQuestion::factory()->create([
            'weekly_challenge_id' => $challenge->id,
            'type' => ChallengeQuestionType::Mcq,
            'correct_choice' => 'A',
            'max_points' => 10,
        ]);
    }

    // 1. User 1 gets 10/10 correct = 100% (>= 95% -> 100 coins)
    $this->actingAs($user1, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);
    $resp1 = $this->actingAs($user1, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => array_map(fn ($q) => ['question_id' => $q->id, 'selected_choice' => 'A'], $questions),
    ]);
    $resp1->assertOk()
        ->assertJson([
            'status' => 'graded',
            'total_score_percent' => 100,
            'reward_coins_awarded' => 100,
            'coin_balance' => 100,
        ]);
    expect($user1->fresh()->coin_balance)->toBe(100);

    // 2. User 2 gets 8/10 correct = 80% (70-94% -> 50 coins)
    $this->actingAs($user2, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);
    $answersUser2 = [];
    foreach ($questions as $idx => $q) {
        $answersUser2[] = [
            'question_id' => $q->id,
            'selected_choice' => $idx < 8 ? 'A' : 'B', // 8 correct, 2 wrong
        ];
    }
    $resp2 = $this->actingAs($user2, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => $answersUser2,
    ]);
    $resp2->assertOk()
        ->assertJson([
            'status' => 'graded',
            'total_score_percent' => 80,
            'reward_coins_awarded' => 50,
            'coin_balance' => 50,
        ]);
    expect($user2->fresh()->coin_balance)->toBe(50);

    // 3. User 3 gets 6/10 correct = 60% (< 70% -> 0 coins)
    $this->actingAs($user3, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);
    $answersUser3 = [];
    foreach ($questions as $idx => $q) {
        $answersUser3[] = [
            'question_id' => $q->id,
            'selected_choice' => $idx < 6 ? 'A' : 'B', // 6 correct, 4 wrong
        ];
    }
    $resp3 = $this->actingAs($user3, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => $answersUser3,
    ]);
    $resp3->assertOk()
        ->assertJson([
            'status' => 'graded',
            'total_score_percent' => 60,
            'reward_coins_awarded' => 0,
            'coin_balance' => 0,
        ]);
    expect($user3->fresh()->coin_balance)->toBe(0);
    expect(CoinTransaction::where('user_id', $user3->id)->count())->toBe(0);
});

test('challenge with structural questions stays submitted until admin grades every structural answer', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create(['coin_balance' => 0]);

    $challenge = WeeklyChallenge::factory()->create([
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
        'time_limit_minutes' => 30,
    ]);

    $mcq = WeeklyChallengeQuestion::factory()->create([
        'weekly_challenge_id' => $challenge->id,
        'type' => ChallengeQuestionType::Mcq,
        'correct_choice' => 'A',
        'max_points' => 50,
    ]);

    $structural = WeeklyChallengeQuestion::factory()->structural()->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'Explain Newton\'s second law with an example.',
        'max_points' => 50,
    ]);

    // Student starts and submits
    $this->actingAs($student, 'sanctum')->postJson("/api/challenges/{$challenge->id}/start")->assertStatus(201);
    $submitResp = $this->actingAs($student, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => [
            ['question_id' => $mcq->id, 'selected_choice' => 'A'],
            ['question_id' => $structural->id, 'answer_text' => 'F = ma. When force increases, acceleration increases.'],
        ],
    ]);

    $submitResp->assertOk()
        ->assertJson([
            'status' => 'submitted',
        ]);

    $attempt = UserChallengeAttempt::where('user_id', $student->id)->where('weekly_challenge_id', $challenge->id)->first();
    expect($attempt->status)->toBe(ChallengeAttemptStatus::Submitted);
    expect($attempt->total_score_percent)->toBeNull();
    expect($student->fresh()->coin_balance)->toBe(0);

    // Student polls result endpoint
    $resultResp = $this->actingAs($student, 'sanctum')->getJson("/api/challenges/{$challenge->id}/result");
    $resultResp->assertOk()
        ->assertJson([
            'has_attempted' => true,
            'status' => 'submitted',
            'attempt' => [
                'status' => 'submitted',
                'total_score_percent' => null,
            ],
        ]);

    // Admin grades structural answer via ChallengeService (e.g. awards 45 / 50 points -> total 95/100 = 95% -> 100 coins)
    $structuralAnswer = UserChallengeAnswer::where('attempt_id', $attempt->id)->where('question_id', $structural->id)->first();
    expect($structuralAnswer->points_awarded)->toBeNull();

    $service = app(ChallengeService::class);
    $gradeResult = $service->gradeStructuralAnswer($admin, $structuralAnswer, 45);

    expect($gradeResult['attempt_status'])->toBe(ChallengeAttemptStatus::Graded->value);
    expect($gradeResult['total_score_percent'])->toBe(95.0);
    expect($gradeResult['reward_coins_awarded'])->toBe(100);

    expect($student->fresh()->coin_balance)->toBe(100);

    // Final poll by student
    $finalResult = $this->actingAs($student, 'sanctum')->getJson("/api/challenges/{$challenge->id}/result");
    $finalResult->assertOk()
        ->assertJson([
            'status' => 'graded',
            'attempt' => [
                'status' => 'graded',
                'total_score_percent' => 95,
                'reward_coins_awarded' => 100,
            ],
        ]);
});
