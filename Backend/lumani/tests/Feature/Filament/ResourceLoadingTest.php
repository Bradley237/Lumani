<?php

use App\Models\Chapter;
use App\Models\DailyCheckinReward;
use App\Models\Mission;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WeeklyChallenge;

test('unauthenticated users are redirected from filament admin panel', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});

test('non-admin users cannot access filament admin panel', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)->get('/admin');

    $response->assertForbidden();
});

test('admin users can access filament admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
});

test('admin can access subjects index and create page', function () {
    $admin = User::factory()->admin()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($admin)->get('/admin/subjects')->assertOk();
    $this->actingAs($admin)->get('/admin/subjects/create')->assertOk();
    $this->actingAs($admin)->get("/admin/subjects/{$subject->id}/edit")->assertOk();
});

test('admin can access chapters index and create page', function () {
    $admin = User::factory()->admin()->create();
    $chapter = Chapter::factory()->create();

    $this->actingAs($admin)->get('/admin/chapters')->assertOk();
    $this->actingAs($admin)->get('/admin/chapters/create')->assertOk();
    $this->actingAs($admin)->get("/admin/chapters/{$chapter->id}/edit")->assertOk();
});

test('admin can access quizzes index and create page', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->create();

    $this->actingAs($admin)->get('/admin/quizzes')->assertOk();
    $this->actingAs($admin)->get('/admin/quizzes/create')->assertOk();
    $this->actingAs($admin)->get("/admin/quizzes/{$quiz->id}/edit")->assertOk();
});

test('admin can access questions index and create page', function () {
    $admin = User::factory()->admin()->create();
    $question = Question::factory()->create();

    $this->actingAs($admin)->get('/admin/questions')->assertOk();
    $this->actingAs($admin)->get('/admin/questions/create')->assertOk();
    $this->actingAs($admin)->get("/admin/questions/{$question->id}/edit")->assertOk();
});

test('admin can access missions index and create page', function () {
    $admin = User::factory()->admin()->create();
    $mission = Mission::factory()->create();

    $this->actingAs($admin)->get('/admin/missions')->assertOk();
    $this->actingAs($admin)->get('/admin/missions/create')->assertOk();
    $this->actingAs($admin)->get("/admin/missions/{$mission->id}/edit")->assertOk();
});

test('admin can access daily checkin rewards index and create page', function () {
    $admin = User::factory()->admin()->create();
    $reward = DailyCheckinReward::factory()->create();

    $this->actingAs($admin)->get('/admin/daily-checkin-rewards')->assertOk();
    $this->actingAs($admin)->get('/admin/daily-checkin-rewards/create')->assertOk();
    $this->actingAs($admin)->get("/admin/daily-checkin-rewards/{$reward->id}/edit")->assertOk();
});

test('admin can access past papers index and create page', function () {
    $admin = User::factory()->admin()->create();
    $pastPaper = PastPaper::factory()->create();

    $this->actingAs($admin)->get('/admin/past-papers')->assertOk();
    $this->actingAs($admin)->get('/admin/past-papers/create')->assertOk();
    $this->actingAs($admin)->get("/admin/past-papers/{$pastPaper->id}/edit")->assertOk();
});

test('admin can access subscriptions index and create page', function () {
    $admin = User::factory()->admin()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($admin)->get('/admin/subscriptions')->assertOk();
    $this->actingAs($admin)->get('/admin/subscriptions/create')->assertOk();
    $this->actingAs($admin)->get("/admin/subscriptions/{$subscription->id}/edit")->assertOk();
});

test('admin can access app settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/settings')->assertOk();
});

test('admin can access weekly challenges index and create page', function () {
    $admin = User::factory()->admin()->create();
    $challenge = WeeklyChallenge::factory()->create();

    $this->actingAs($admin)->get('/admin/weekly-challenges')->assertOk();
    $this->actingAs($admin)->get('/admin/weekly-challenges/create')->assertOk();
    $this->actingAs($admin)->get("/admin/weekly-challenges/{$challenge->id}/edit")->assertOk();
});

test('admin can access grading queue page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/grading-queue')->assertOk();
});
