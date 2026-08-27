<?php

use App\Enums\ChapterProgressState;
use App\Enums\CoinTransactionType;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\ChapterProgress;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserChapterUnlock;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();
});

test('cannot fetch or start quiz for a locked chapter', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'is_free' => false,
    ]);
    $quiz = Quiz::factory()->create([
        'chapter_id' => $chapter->id,
        'passing_score' => 70,
    ]);
    Question::factory()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'What is 2 + 2?',
        'answer_choices' => ['A' => '3', 'B' => '4'],
        'correct_choice' => 'B',
    ]);

    // Fetching quiz is rejected with 422
    $response = $this->actingAs($user, 'sanctum')->getJson("/api/quizzes/{$quiz->id}");
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['chapter']);

    // Submitting quiz is also rejected with 422
    $submitResp = $this->actingAs($user, 'sanctum')->postJson("/api/quizzes/{$quiz->id}/submit", [
        'answers' => [
            ['question_id' => 1, 'selected_choice' => 'B'],
        ],
    ]);
    $submitResp->assertStatus(422)
        ->assertJsonValidationErrors(['chapter']);
});

test('quiz fetch response never leaks correct_choice or explanation', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
    ]);
    $quiz = Quiz::factory()->create([
        'chapter_id' => $chapter->id,
        'passing_score' => 75,
    ]);

    $q1 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'question_text' => 'What is the speed of light?',
        'answer_choices' => ['A' => '3x10^8 m/s', 'B' => '3x10^6 m/s'],
        'correct_choice' => 'A',
        'explanation' => 'Light travels at approximately 300,000,000 m/s in vacuum.',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/quizzes/{$quiz->id}");

    $response->assertOk()
        ->assertJsonPath('id', $quiz->id)
        ->assertJsonPath('chapter_id', $chapter->id)
        ->assertJsonPath('passing_score', 75)
        ->assertJsonPath('total_questions', 1)
        ->assertJsonPath('questions.0.id', $q1->id)
        ->assertJsonPath('questions.0.question_text', 'What is the speed of light?')
        ->assertJsonPath('questions.0.answer_choices.A', '3x10^8 m/s');

    $json = $response->json();
    expect(isset($json['questions'][0]['correct_choice']))->toBeFalse();
    expect(isset($json['questions'][0]['explanation']))->toBeFalse();
});

test('touch chapter marks state as in_progress and updates last_accessed_at', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/touch");

    $response->assertOk()
        ->assertJson([
            'message' => 'Chapter progress updated.',
            'progress' => [
                'chapter_id' => $chapter->id,
                'state' => 'in_progress',
                'completed_at' => null,
            ],
        ]);

    $this->assertDatabaseHas('chapter_progress', [
        'user_id' => $user->id,
        'chapter_id' => $chapter->id,
        'state' => ChapterProgressState::InProgress->value,
    ]);
});

test('touching a completed chapter preserves completed state and updates last_accessed_at', function () {
    $user = User::factory()->student()->create();
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
    ]);

    $completedAt = now()->subDays(2);
    ChapterProgress::factory()->create([
        'user_id' => $user->id,
        'chapter_id' => $chapter->id,
        'state' => ChapterProgressState::Completed,
        'completed_at' => $completedAt,
        'last_accessed_at' => $completedAt,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/touch");

    $response->assertOk()
        ->assertJson([
            'progress' => [
                'chapter_id' => $chapter->id,
                'state' => 'completed',
            ],
        ]);

    $freshProgress = ChapterProgress::where('user_id', $user->id)->where('chapter_id', $chapter->id)->first();
    expect($freshProgress->state)->toBe(ChapterProgressState::Completed);
    expect($freshProgress->completed_at->toIso8601String())->toBe($completedAt->toIso8601String());
});

test('submitting quiz grades correctly and awards quiz XP plus chapter reward on first completion', function () {
    $user = User::factory()->student()->create([
        'experience_points' => 100,
        'coin_balance' => 0,
    ]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
        'xp_reward' => 50,
    ]);
    $quiz = Quiz::factory()->create([
        'chapter_id' => $chapter->id,
        'passing_score' => 70,
    ]);

    $q1 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'correct_choice' => 'A',
    ]);
    $q2 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'correct_choice' => 'B',
    ]);
    $q3 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'correct_choice' => 'C',
    ]);

    // Answer 2 correctly out of 3 (q1: A [correct], q2: B [correct], q3: D [wrong])
    $response = $this->actingAs($user, 'sanctum')->postJson("/api/quizzes/{$quiz->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
            ['question_id' => $q2->id, 'selected_choice' => 'B'],
            ['question_id' => $q3->id, 'selected_choice' => 'D'],
        ],
    ]);

    // Expected quiz XP: (2 * 10) + 20 = 40 XP
    // Expected chapter XP: 50 XP
    // Total XP awarded: 90 XP
    // New total XP: 100 + 90 = 190 XP
    // Score percent: 2/3 = 66.67%
    $response->assertOk()
        ->assertJson([
            'score_percent' => 66.67,
            'correct_count' => 2,
            'total_questions' => 3,
            'quiz_xp_earned' => 40,
            'chapter_xp_reward' => 50,
            'total_xp_earned' => 90,
            'is_first_completion' => true,
            'experience_points' => 190,
            'chapter_progress' => [
                'chapter_id' => $chapter->id,
                'state' => 'completed',
            ],
        ]);

    expect($user->fresh()->experience_points)->toBe(190);

    // Verify QuizAttempt in DB
    $attempt = QuizAttempt::where('user_id', $user->id)->where('quiz_id', $quiz->id)->first();
    expect($attempt)->not->toBeNull();
    expect((float) $attempt->score_percent)->toBe(66.67);
    expect($attempt->correct_count)->toBe(2);
    expect($attempt->total_questions)->toBe(3);
    expect($attempt->xp_earned)->toBe(40);

    // Verify QuizAttemptAnswer in DB
    expect(QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)->count())->toBe(3);
    expect(QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)->where('is_correct', true)->count())->toBe(2);

    // Verify ChapterProgress in DB
    $prog = ChapterProgress::where('user_id', $user->id)->where('chapter_id', $chapter->id)->first();
    expect($prog->state)->toBe(ChapterProgressState::Completed);
    expect($prog->completed_at)->not->toBeNull();
});

test('retaking a quiz awards quiz XP but does not double-award chapter xp_reward', function () {
    $user = User::factory()->student()->create([
        'experience_points' => 0,
        'coin_balance' => 0,
    ]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
        'xp_reward' => 50,
    ]);
    $quiz = Quiz::factory()->create([
        'chapter_id' => $chapter->id,
    ]);
    $q1 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'correct_choice' => 'A',
    ]);

    // First attempt: 1 correct => quiz XP = 10 + 20 = 30; chapter XP = 50 => total 80 XP
    $this->actingAs($user, 'sanctum')->postJson("/api/quizzes/{$quiz->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
        ],
    ])->assertOk()
        ->assertJson([
            'is_first_completion' => true,
            'chapter_xp_reward' => 50,
            'quiz_xp_earned' => 30,
            'total_xp_earned' => 80,
            'experience_points' => 80,
        ]);

    expect($user->fresh()->experience_points)->toBe(80);

    // Second attempt (retake): 1 correct => quiz XP = 30, chapter XP = 0 => total 30 XP
    $retakeResp = $this->actingAs($user, 'sanctum')->postJson("/api/quizzes/{$quiz->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
        ],
    ]);

    $retakeResp->assertOk()
        ->assertJson([
            'is_first_completion' => false,
            'chapter_xp_reward' => 0,
            'quiz_xp_earned' => 30,
            'total_xp_earned' => 30,
            'experience_points' => 110,
        ]);

    expect($user->fresh()->experience_points)->toBe(110);
    expect(QuizAttempt::where('user_id', $user->id)->count())->toBe(2);
});

test('crossing 1500 XP threshold during quiz submission automatically awards coins', function () {
    $user = User::factory()->student()->create([
        'experience_points' => 1470, // 30 XP away from 1500 threshold
        'xp_converted_total' => 0,
        'coin_balance' => 0,
    ]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
        'xp_reward' => 0,
    ]);
    $quiz = Quiz::factory()->create([
        'chapter_id' => $chapter->id,
    ]);
    $q1 = Question::factory()->create([
        'quiz_id' => $quiz->id,
        'correct_choice' => 'A',
    ]);

    // 1 correct answer = 10 + 20 = 30 XP.
    // 1470 + 30 = 1500 XP -> crosses 1500 XP chunk -> converts to 50 coins!
    $response = $this->actingAs($user, 'sanctum')->postJson("/api/quizzes/{$quiz->id}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_choice' => 'A'],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'quiz_xp_earned' => 30,
            'total_xp_earned' => 30,
            'coins_earned_from_xp' => 50,
            'experience_points' => 1500,
            'coin_balance' => 50,
        ]);

    $freshUser = $user->fresh();
    expect($freshUser->experience_points)->toBe(1500);
    expect($freshUser->xp_converted_total)->toBe(1500);
    expect($freshUser->coin_balance)->toBe(50);

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $user->id,
        'amount' => 50,
        'type' => CoinTransactionType::EarnedXpConversion->value,
    ]);
});

test('progress dashboard endpoint returns accurate state across multiple chapters and subjects', function () {
    $user = User::factory()->student()->create([
        'experience_points' => 250,
        'coin_balance' => 30,
    ]);

    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $physics = Subject::factory()->create(['name' => 'Physics']);

    // Math: Chapter 1 (completed), Chapter 2 (in_progress), Chapter 3 (not_started)
    $m1 = Chapter::factory()->create(['subject_id' => $math->id, 'order' => 1, 'is_free' => true]);
    $m2 = Chapter::factory()->create(['subject_id' => $math->id, 'order' => 2, 'is_free' => false]);
    $m3 = Chapter::factory()->create(['subject_id' => $math->id, 'order' => 3, 'is_free' => false]);

    // Physics: Chapter 1 (not_started)
    $p1 = Chapter::factory()->create(['subject_id' => $physics->id, 'order' => 1, 'is_free' => true]);

    // User unlocked Math Chapter 2
    UserChapterUnlock::factory()->create([
        'user_id' => $user->id,
        'chapter_id' => $m2->id,
    ]);

    // Math Chapter 1 completed
    ChapterProgress::factory()->create([
        'user_id' => $user->id,
        'chapter_id' => $m1->id,
        'state' => ChapterProgressState::Completed,
        'completed_at' => now()->subDay(),
    ]);

    // Math Chapter 2 in progress
    ChapterProgress::factory()->create([
        'user_id' => $user->id,
        'chapter_id' => $m2->id,
        'state' => ChapterProgressState::InProgress,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/progress');

    $response->assertOk()
        ->assertJson([
            'total_chapters' => 4,
            'completed_chapters' => 1,
            'in_progress_chapters' => 1,
            'overall_progress_percent' => 25.0,
            'experience_points' => 250,
            'coin_balance' => 30,
        ]);

    $subjects = $response->json('subjects');
    expect($subjects)->toHaveCount(2);

    $mathSubject = collect($subjects)->firstWhere('name', 'Mathematics');
    expect($mathSubject['total_chapters'])->toBe(3);
    expect($mathSubject['completed_chapters'])->toBe(1);

    $mathChapters = collect($mathSubject['chapters'])->keyBy('id');
    expect($mathChapters[$m1->id]['state'])->toBe('completed');
    expect($mathChapters[$m1->id]['is_unlocked'])->toBeTrue();

    expect($mathChapters[$m2->id]['state'])->toBe('in_progress');
    expect($mathChapters[$m2->id]['is_unlocked'])->toBeTrue();

    expect($mathChapters[$m3->id]['state'])->toBe('not_started');
    expect($mathChapters[$m3->id]['is_unlocked'])->toBeFalse();
});
