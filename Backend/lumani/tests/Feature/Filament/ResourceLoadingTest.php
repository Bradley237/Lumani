<?php

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;

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
