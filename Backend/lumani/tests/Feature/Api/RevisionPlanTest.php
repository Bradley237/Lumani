<?php

use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RevisionPlan;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();
});

test('revision plan generation is free for all authenticated students without subscription', function () {
    $user = User::factory()->student()->create();

    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    Chapter::factory()->create(['subject_id' => $subject->id, 'title' => 'Calculus']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 180,
        'available_days' => [1, 3, 5],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('plan.weekly_available_minutes', 180)
        ->assertJsonPath('plan.available_days', [1, 3, 5])
        ->assertJsonCount(3, 'plan.plan_data');
});

test('weaker subjects receive proportionally more allocated time than stronger subjects', function () {
    $user = User::factory()->student()->create();

    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $physics = Subject::factory()->create(['name' => 'Physics']);

    $mChapter = Chapter::factory()->create(['subject_id' => $math->id, 'title' => 'Algebra']);
    $pChapter = Chapter::factory()->create(['subject_id' => $physics->id, 'title' => 'Mechanics']);

    $mQuiz = Quiz::factory()->create(['chapter_id' => $mChapter->id]);
    $pQuiz = Quiz::factory()->create(['chapter_id' => $pChapter->id]);

    // Math is Strong (90% average)
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $mQuiz->id, 'score_percent' => 90.0]);

    // Physics is Weak (30% average)
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $pQuiz->id, 'score_percent' => 30.0]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 200,
        'available_days' => [1, 3], // 2 days: day 1 (Physics), day 3 (Math)
    ]);

    $response->assertStatus(201);
    $planData = $response->json('plan.plan_data');

    $physicsAllocation = collect($planData)->firstWhere('subject_id', $physics->id);
    $mathAllocation = collect($planData)->firstWhere('subject_id', $math->id);

    expect($physicsAllocation)->not->toBeNull();
    expect($mathAllocation)->not->toBeNull();
    // Weaker physics gets significantly more minutes than strong math
    expect($physicsAllocation['duration_minutes'])->toBeGreaterThan($mathAllocation['duration_minutes']);

    // Total equals weeklyAvailableMinutes
    $totalAllocated = collect($planData)->sum('duration_minutes');
    expect($totalAllocated)->toBe(200);
});

test('unattempted subjects are prioritized as highest priority and not ignored', function () {
    $user = User::factory()->student()->create();

    $chemistry = Subject::factory()->create(['name' => 'Chemistry']);
    $math = Subject::factory()->create(['name' => 'Mathematics']);

    $cChapter = Chapter::factory()->create(['subject_id' => $chemistry->id]);
    $mChapter = Chapter::factory()->create(['subject_id' => $math->id]);

    $mQuiz = Quiz::factory()->create(['chapter_id' => $mChapter->id]);

    // Math has 80% score, Chemistry has 0 attempts (unassessed)
    QuizAttempt::factory()->create(['user_id' => $user->id, 'quiz_id' => $mQuiz->id, 'score_percent' => 80.0]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 300,
        'available_days' => [1, 2],
    ]);

    $response->assertStatus(201);
    $planData = $response->json('plan.plan_data');

    $chemAllocation = collect($planData)->firstWhere('subject_id', $chemistry->id);
    $mathAllocation = collect($planData)->firstWhere('subject_id', $math->id);

    // Unassessed Chemistry gets top priority weight (100) vs Math (20)
    expect($chemAllocation['duration_minutes'])->toBeGreaterThan($mathAllocation['duration_minutes']);
});

test('total allocated minutes never exceeds weekly_available_minutes', function () {
    $user = User::factory()->student()->create();

    for ($i = 1; $i <= 5; $i++) {
        $sub = Subject::factory()->create(['name' => "Subject {$i}"]);
        Chapter::factory()->create(['subject_id' => $sub->id]);
    }

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 375,
        'available_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $response->assertStatus(201);
    $planData = $response->json('plan.plan_data');

    $totalAllocated = collect($planData)->sum('duration_minutes');
    expect($totalAllocated)->toBe(375);
    expect($totalAllocated)->toBeLessThanOrEqual(375);
});

test('regenerating creates a new row while GET revision-plan returns the latest', function () {
    $user = User::factory()->student()->create();
    $sub = Subject::factory()->create();
    Chapter::factory()->create(['subject_id' => $sub->id]);

    // 1. Initial plan (120 mins)
    $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 120,
        'available_days' => [1, 2],
    ])->assertStatus(201);

    // 2. Second plan (240 mins)
    $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 240,
        'available_days' => [3, 4, 5],
    ])->assertStatus(201);

    expect(RevisionPlan::where('user_id', $user->id)->count())->toBe(2);

    // 3. GET /api/revision-plan returns the latest 240-minute plan
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/revision-plan');
    $response->assertOk()
        ->assertJson([
            'has_plan' => true,
            'plan' => [
                'weekly_available_minutes' => 240,
                'available_days' => [3, 4, 5],
            ],
        ]);
});

test('returns clear empty state when no plan has been generated yet', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/revision-plan');
    $response->assertOk()
        ->assertJson([
            'has_plan' => false,
            'plan' => null,
        ]);
});

test('handles edge case when no subjects or chapters exist with fallback plan', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/revision-plan/generate', [
        'weekly_available_minutes' => 150,
        'available_days' => [2],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('plan.weekly_available_minutes', 150)
        ->assertJsonPath('plan.plan_data.0.chapter_title', 'Explore your first free chapters');
});
