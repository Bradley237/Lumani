<?php

use App\Filament\Resources\WeeklyChallenges\Pages\CreateWeeklyChallenge;
use App\Models\Subject;
use App\Models\User;
use App\Models\WeeklyChallenge;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('admin can render create weekly challenge page and create a record', function () {
    $admin = User::factory()->admin()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateWeeklyChallenge::class)
        ->assertSuccessful()
        ->fillForm([
            'subject_id' => $subject->id,
            'title' => 'Test Challenge',
            'time_limit_minutes' => 30,
            'status' => 'published',
            'week_start_date' => now()->startOfWeek()->toDateTimeString(),
            'week_end_date' => now()->endOfWeek()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WeeklyChallenge::where('title', 'Test Challenge')->exists())->toBeTrue();
    $challenge = WeeklyChallenge::where('title', 'Test Challenge')->first();
    expect($challenge->created_by)->toBe($admin->id);
});

test('admin can create weekly challenge with questions', function () {
    $admin = User::factory()->admin()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateWeeklyChallenge::class)
        ->fillForm([
            'subject_id' => $subject->id,
            'title' => 'Challenge With Questions',
            'time_limit_minutes' => 45,
            'status' => 'published',
            'week_start_date' => now()->startOfWeek()->toDateTimeString(),
            'week_end_date' => now()->endOfWeek()->toDateTimeString(),
            'questions' => [
                [
                    'type' => 'mcq',
                    'question_text' => 'What is 5 x 5?',
                    'options' => ['A' => '25', 'B' => '20'],
                    'correct_choice' => 'A',
                    'max_points' => 10,
                    'order' => 1,
                ],
                [
                    'type' => 'structural',
                    'question_text' => 'Describe photosynthesis in brief.',
                    'max_points' => 20,
                    'order' => 2,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $challenge = WeeklyChallenge::where('title', 'Challenge With Questions')->first();
    expect($challenge)->not->toBeNull();
    expect($challenge->questions()->count())->toBe(2);
});
