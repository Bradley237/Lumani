<?php

use App\Enums\ExamSessionStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Filament\Pages\GradingQueue;
use App\Models\AppSetting;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();
    Config::set('services.gemini.api_key', 'test-gemini-api-key');
});

test('cannot start exam session without an active subscription', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $pastPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/exam-session/start");
    $response->assertStatus(403);
});

test('free mode allows starting exam session without an active subscription', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $pastPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    PastPaperQuestion::factory()->create(['past_paper_id' => $pastPaper->id]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/exam-session/start");
    $response->assertStatus(201)
        ->assertJsonPath('session.past_paper_id', $pastPaper->id)
        ->assertJsonPath('session.status', 'in_progress');
});

test('computes max_allowed_minutes correctly for MCQ-only, structural-only, and mixed compositions', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'tier' => SubscriptionTier::Tier2000,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);

    $subject = Subject::factory()->create();

    // 1. MCQ-only paper => 90 mins
    $mcqPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    PastPaperQuestion::factory()->count(2)->create(['past_paper_id' => $mcqPaper->id]);

    $resp1 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$mcqPaper->id}/exam-session/start");
    $resp1->assertStatus(201)
        ->assertJsonPath('session.max_allowed_minutes', 90)
        ->assertJsonPath('session.selected_minutes', 90);

    // 2. Structural-only paper => 180 mins
    $structuralPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    PastPaperQuestion::factory()->structural()->count(2)->create(['past_paper_id' => $structuralPaper->id]);

    $resp2 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$structuralPaper->id}/exam-session/start");
    $resp2->assertStatus(201)
        ->assertJsonPath('session.max_allowed_minutes', 180)
        ->assertJsonPath('session.selected_minutes', 180);

    // 3. Mixed paper => 240 mins
    $mixedPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    PastPaperQuestion::factory()->create(['past_paper_id' => $mixedPaper->id]);
    PastPaperQuestion::factory()->structural()->create(['past_paper_id' => $mixedPaper->id]);

    $resp3 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$mixedPaper->id}/exam-session/start");
    $resp3->assertStatus(201)
        ->assertJsonPath('session.max_allowed_minutes', 240)
        ->assertJsonPath('session.selected_minutes', 240);
});

test('rejects a requested duration above max_allowed_minutes', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);
    $subject = Subject::factory()->create();
    $mcqPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    PastPaperQuestion::factory()->create(['past_paper_id' => $mcqPaper->id]); // max 90 mins

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$mcqPaper->id}/exam-session/start", [
        'requested_minutes' => 120, // Exceeds 90 mins cap
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['requested_minutes']);
});

test('session-start response never leaks correct_choice or marking_scheme', function () {
    $user = User::factory()->student()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Active,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ]);
    $subject = Subject::factory()->create();
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $q1 = PastPaperQuestion::factory()->create([
        'past_paper_id' => $paper->id,
        'question_text' => 'What is the capital of Cameroon?',
        'correct_choice' => 'Yaounde',
    ]);
    $q2 = PastPaperQuestion::factory()->structural('Detailed criteria: 5 marks for definition, 5 for formula')->create([
        'past_paper_id' => $paper->id,
        'question_text' => 'Explain photosynthesis.',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$paper->id}/exam-session/start");

    $response->assertStatus(201);
    $json = $response->json();

    expect(isset($json['questions'][0]['correct_choice']))->toBeFalse();
    expect(isset($json['questions'][0]['marking_scheme']))->toBeFalse();
    expect(isset($json['questions'][1]['marking_scheme']))->toBeFalse();
});

test('rejects submission past the student selected duration with tolerance', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id]);
    $q1 = PastPaperQuestion::factory()->create(['past_paper_id' => $paper->id]);

    // Started 92 minutes ago with 90 minutes selected (exceeds 90 min + 60s tolerance)
    $session = ExamSession::factory()->create([
        'user_id' => $user->id,
        'past_paper_id' => $paper->id,
        'max_allowed_minutes' => 90,
        'selected_minutes' => 90,
        'started_at' => now()->subMinutes(92),
        'status' => ExamSessionStatus::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/exam-sessions/{$session->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['time_limit']);
});

test('MCQ-only session grades instantly upon submission without awarding coins', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);
    $subject = Subject::factory()->create();
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $q1 = PastPaperQuestion::factory()->create([
        'past_paper_id' => $paper->id,
        'correct_choice' => 'A',
        'max_points' => 10,
    ]);
    $q2 = PastPaperQuestion::factory()->create([
        'past_paper_id' => $paper->id,
        'correct_choice' => 'B',
        'max_points' => 10,
    ]);

    $session = ExamSession::factory()->create([
        'user_id' => $user->id,
        'past_paper_id' => $paper->id,
        'max_allowed_minutes' => 90,
        'selected_minutes' => 90,
        'started_at' => now()->subMinutes(20),
        'status' => ExamSessionStatus::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/exam-sessions/{$session->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'], // correct (10)
            ['question_id' => $q2->id, 'selected_choice' => 'C'], // wrong (0)
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'graded',
            'total_score_percent' => 50.0,
            'session_id' => $session->id,
        ]);

    $session->refresh();
    expect($session->status)->toBe(ExamSessionStatus::Graded);
    expect((float) $session->total_score_percent)->toBe(50.0);

    // No coins awarded
    expect($user->fresh()->coin_balance)->toBe(0);
});

test('mixed sessions pre-populate AI suggestion on structural answers and wait for admin grading', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'suggested_points' => 18,
                                    'justification' => 'Clear calculation of acceleration and net force.',
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
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $q1 = PastPaperQuestion::factory()->create([
        'past_paper_id' => $paper->id,
        'correct_choice' => 'A',
        'max_points' => 10,
    ]);
    $q2 = PastPaperQuestion::factory()->structural('Expected formula F=ma and correct substitution')->create([
        'past_paper_id' => $paper->id,
        'max_points' => 20,
    ]);

    $session = ExamSession::factory()->create([
        'user_id' => $user->id,
        'past_paper_id' => $paper->id,
        'max_allowed_minutes' => 240,
        'selected_minutes' => 240,
        'started_at' => now()->subMinutes(30),
        'status' => ExamSessionStatus::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/exam-sessions/{$session->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
            ['question_id' => $q2->id, 'answer_text' => 'F = m*a = 5kg * 2m/s^2 = 10N.'],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'submitted',
            'session_id' => $session->id,
        ]);

    $session->refresh();
    expect($session->status)->toBe(ExamSessionStatus::Submitted);
    expect($session->total_score_percent)->toBeNull();

    $structuralAns = ExamSessionAnswer::where('exam_session_id', $session->id)
        ->where('question_id', $q2->id)
        ->first();

    expect($structuralAns)->not->toBeNull();
    expect($structuralAns->points_awarded)->toBeNull();
    expect($structuralAns->suggested_points)->toBe(18);
    expect($structuralAns->suggested_justification)->toBe('Clear calculation of acceleration and net force.');
});

test('grading queue displays exam session structural answers and allows admin to grade or override', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id, 'title' => 'Physics 2024 Paper 2']);

    $q1 = PastPaperQuestion::factory()->structural('Marking scheme details')->create([
        'past_paper_id' => $paper->id,
        'question_text' => 'State the laws of refraction.',
        'max_points' => 15,
    ]);

    $session = ExamSession::factory()->create([
        'user_id' => $student->id,
        'past_paper_id' => $paper->id,
        'status' => ExamSessionStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $answer = ExamSessionAnswer::factory()->structural(12, 'Accurate Snell law statement, missing diagram')->create([
        'exam_session_id' => $session->id,
        'question_id' => $q1->id,
        'points_awarded' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(GradingQueue::class)
        ->assertSuccessful()
        ->assertSee('Physics 2024 Paper 2')
        ->assertSee('State the laws of refraction.')
        ->assertSee('Suggested Score: 12 / 15 pts')
        ->assertSee('Accurate Snell law statement, missing diagram')
        ->assertSee('Marking scheme details');

    // Pre-filled grade
    expect($component->get("examGrades.{$answer->id}"))->toBe('12');

    // Override score to 14
    $component->set("examGrades.{$answer->id}", '14')
        ->call('gradeExamAnswer', $answer->id)
        ->assertHasNoErrors();

    $answer->refresh();
    expect($answer->points_awarded)->toBe(14);
    expect($answer->suggested_justification)->toBe('Accurate Snell law statement, missing diagram');

    $session->refresh();
    expect($session->status)->toBe(ExamSessionStatus::Graded);
    expect((float) $session->total_score_percent)->toBe(round((14 / 15) * 100, 2));
});

test('exam session result endpoint returns feedback text to student', function () {
    $student = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $paper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    $q1 = PastPaperQuestion::factory()->structural()->create([
        'past_paper_id' => $paper->id,
        'question_text' => 'State Ohm law.',
        'max_points' => 10,
    ]);

    $session = ExamSession::factory()->create([
        'user_id' => $student->id,
        'past_paper_id' => $paper->id,
        'status' => ExamSessionStatus::Graded,
        'started_at' => now()->subMinutes(40),
        'submitted_at' => now()->subMinutes(5),
        'total_score_percent' => 90.0,
    ]);

    ExamSessionAnswer::factory()->create([
        'exam_session_id' => $session->id,
        'question_id' => $q1->id,
        'answer_text' => 'V is directly proportional to I at constant temperature.',
        'points_awarded' => 9,
        'suggested_points' => 9,
        'suggested_justification' => 'Correct statement and conditions specified.',
    ]);

    $response = $this->actingAs($student, 'sanctum')->getJson("/api/exam-sessions/{$session->id}/result");

    $response->assertOk()
        ->assertJsonPath('status', 'graded')
        ->assertJsonPath('result.total_score_percent', 90)
        ->assertJsonPath('result.answers.0.points_awarded', 9)
        ->assertJsonPath('result.answers.0.feedback', 'Correct statement and conditions specified.');
});
