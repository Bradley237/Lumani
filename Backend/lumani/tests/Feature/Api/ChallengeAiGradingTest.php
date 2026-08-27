<?php

use App\Enums\ChallengeAttemptStatus;
use App\Enums\ChallengeStatus;
use App\Filament\Pages\GradingQueue;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserChallengeAnswer;
use App\Models\UserChallengeAttempt;
use App\Models\WeeklyChallenge;
use App\Models\WeeklyChallengeQuestion;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    Config::set('services.gemini.api_key', 'test-gemini-api-key');
});

test('submitting structural answer generates and stores AI suggested score and justification', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'suggested_points' => 16,
                                    'justification' => 'Accurate explanation of Newton second law with clear derivation of F=ma.',
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
        'time_limit_minutes' => 30,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->structural('Expected: F=dp/dt and derivation steps leading to F=ma.')->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'State Newton second law and derive F = ma.',
        'max_points' => 20,
    ]);

    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $user->id,
        'weekly_challenge_id' => $challenge->id,
        'started_at' => now(),
        'status' => ChallengeAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => [
            [
                'question_id' => $q1->id,
                'answer_text' => 'Force is proportional to the rate of change of momentum. dp/dt = d(mv)/dt = m(dv/dt) = ma.',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'submitted',
        ]);

    $answer = UserChallengeAnswer::where('attempt_id', $attempt->id)
        ->where('question_id', $q1->id)
        ->first();

    expect($answer)->not->toBeNull();
    expect($answer->points_awarded)->toBeNull();
    expect($answer->suggested_points)->toBe(16);
    expect($answer->suggested_justification)->toBe('Accurate explanation of Newton second law with clear derivation of F=ma.');
});

test('submission succeeds and handles Gemini failure gracefully without blocking student or grading', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Rate limit exceeded'], 500),
    ]);

    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'status' => ChallengeStatus::Published,
        'week_start_date' => now()->subDay(),
        'week_end_date' => now()->addDays(5),
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->structural()->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'Explain Hookes Law.',
        'max_points' => 10,
    ]);

    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $user->id,
        'weekly_challenge_id' => $challenge->id,
        'started_at' => now(),
        'status' => ChallengeAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/challenges/{$challenge->id}/submit", [
        'answers' => [
            [
                'question_id' => $q1->id,
                'answer_text' => 'Extension is directly proportional to load provided limit of proportionality is not exceeded.',
            ],
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'submitted']);

    $answer = UserChallengeAnswer::where('attempt_id', $attempt->id)->first();
    expect($answer)->not->toBeNull();
    expect($answer->suggested_points)->toBeNull();
    expect($answer->suggested_justification)->toBeNull();
    expect($answer->points_awarded)->toBeNull();
});

test('grading queue displays suggested score and justification, pre-fills score, and allows admin to keep or override', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'status' => ChallengeStatus::Published,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->structural('Marking scheme details')->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'Describe electrolysis.',
        'max_points' => 15,
    ]);

    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $student->id,
        'weekly_challenge_id' => $challenge->id,
        'status' => ChallengeAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $answer = UserChallengeAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer_text' => 'Chemical decomposition produced by passing an electric current through a liquid.',
        'points_awarded' => null,
        'suggested_points' => 12,
        'suggested_justification' => 'Good core definition, missing cathode/anode specific reactions.',
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(GradingQueue::class)
        ->assertSuccessful()
        ->assertSee('Describe electrolysis.')
        ->assertSee('Suggested Score: 12 / 15 pts')
        ->assertSee('Good core definition, missing cathode/anode specific reactions.')
        ->assertSee('Marking scheme details');

    // Check pre-filled grade
    expect($component->get("grades.{$answer->id}"))->toBe('12');

    // Admin overrides score to 14
    $component->set("grades.{$answer->id}", '14')
        ->call('gradeAnswer', $answer->id)
        ->assertHasNoErrors();

    $answer->refresh();
    // Points awarded is strictly the admin's override (14)
    expect($answer->points_awarded)->toBe(14);
    // AI justification is preserved intact
    expect($answer->suggested_justification)->toBe('Good core definition, missing cathode/anode specific reactions.');
});

test('challenge result endpoint returns feedback text to student', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'status' => ChallengeStatus::Published,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->structural()->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'Define speed.',
        'max_points' => 10,
    ]);

    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $student->id,
        'weekly_challenge_id' => $challenge->id,
        'status' => ChallengeAttemptStatus::Graded,
        'started_at' => now()->subMinutes(10),
        'submitted_at' => now()->subMinutes(2),
        'total_score_percent' => 80.0,
        'reward_coins_awarded' => 50,
    ]);

    UserChallengeAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer_text' => 'Distance divided by time.',
        'points_awarded' => 8,
        'suggested_points' => 8,
        'suggested_justification' => 'Correct scalar definition.',
    ]);

    $response = $this->actingAs($student, 'sanctum')->getJson("/api/challenges/{$challenge->id}/result");

    $response->assertOk()
        ->assertJsonPath('status', 'graded')
        ->assertJsonPath('attempt.answers.0.points_awarded', 8)
        ->assertJsonPath('attempt.answers.0.feedback', 'Correct scalar definition.');
});

test('challenge result endpoint handles missing feedback gracefully without errors', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();

    $challenge = WeeklyChallenge::factory()->create([
        'subject_id' => $subject->id,
        'status' => ChallengeStatus::Published,
    ]);

    $q1 = WeeklyChallengeQuestion::factory()->structural()->create([
        'weekly_challenge_id' => $challenge->id,
        'question_text' => 'Define velocity.',
        'max_points' => 10,
    ]);

    $attempt = UserChallengeAttempt::factory()->create([
        'user_id' => $student->id,
        'weekly_challenge_id' => $challenge->id,
        'status' => ChallengeAttemptStatus::Graded,
        'started_at' => now()->subMinutes(10),
        'submitted_at' => now()->subMinutes(2),
        'total_score_percent' => 100.0,
        'reward_coins_awarded' => 100,
    ]);

    UserChallengeAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer_text' => 'Rate of change of displacement.',
        'points_awarded' => 10,
        'suggested_points' => null,
        'suggested_justification' => null,
    ]);

    $response = $this->actingAs($student, 'sanctum')->getJson("/api/challenges/{$challenge->id}/result");

    $response->assertOk()
        ->assertJsonPath('status', 'graded')
        ->assertJsonPath('attempt.answers.0.points_awarded', 10);

    // Feedback should either be absent or null, but never throw an error
    $answerJson = $response->json('attempt.answers.0');
    expect(isset($answerJson['feedback']) ? $answerJson['feedback'] : null)->toBeNull();
});
