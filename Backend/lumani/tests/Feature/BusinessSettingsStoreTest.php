<?php

use App\Enums\PastPaperQuestionType;
use App\Enums\UserRole;
use App\Filament\Pages\ManageBusinessSettings;
use App\Models\BusinessSetting;
use App\Models\Chapter;
use App\Models\PastPaper;
use App\Models\PastPaperQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdRewardService;
use App\Services\ChallengeService;
use App\Services\ExamSessionService;
use App\Services\MissionService;
use App\Services\QuizService;
use App\Services\XpService;
use Carbon\Carbon;
use Database\Seeders\BusinessSettingSeeder;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessSettingSeeder::class);
    $this->seed(MissionAndRewardSeeder::class);
    BusinessSetting::flushRuntimeCache();
});

test('BusinessSetting::get reads, type-casts, and caches values in memory', function () {
    // Integer
    $intVal = BusinessSetting::get('quiz_xp_per_correct_answer');
    expect($intVal)->toBeInt()->toBe(10);

    // Decimal
    $decVal = BusinessSetting::get('challenge_reward_high_threshold_percent');
    expect($decVal)->toBeFloat()->toBe(95.0);

    // In-memory caching: DB should not be queried again for the same key
    DB::enableQueryLog();
    $cachedVal = BusinessSetting::get('quiz_xp_per_correct_answer');
    $queries = DB::getQueryLog();
    expect($cachedVal)->toBe(10)
        ->and($queries)->toBeEmpty();
    DB::disableQueryLog();

    // Default fallback
    $fallback = BusinessSetting::get('non_existent_key_xyz', 999);
    expect($fallback)->toBe(999);
});

test('QuizService respects custom quiz XP settings from BusinessSetting', function () {
    BusinessSetting::set('quiz_xp_per_correct_answer', 25);
    BusinessSetting::set('quiz_xp_completion_bonus', 50);

    $user = User::factory()->student()->create(['coin_balance' => 0, 'experience_points' => 0]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create(['subject_id' => $subject->id, 'xp_reward' => 0]);
    $quiz = Quiz::factory()->create(['chapter_id' => $chapter->id, 'passing_score' => 50]);

    $q1 = Question::factory()->create(['quiz_id' => $quiz->id, 'correct_choice' => 'A']);
    $q2 = Question::factory()->create(['quiz_id' => $quiz->id, 'correct_choice' => 'B']);

    // Student unlocks chapter
    $user->chapterUnlocks()->create([
        'chapter_id' => $chapter->id,
        'unlocked_at' => now(),
    ]);

    /** @var QuizService $quizService */
    $quizService = app(QuizService::class);
    $result = $quizService->submitQuiz($user, $quiz, [
        ['question_id' => $q1->id, 'selected_choice' => 'A'], // Correct
        ['question_id' => $q2->id, 'selected_choice' => 'B'], // Correct
    ]);

    // Expected: 2 correct * 25 XP + 50 bonus = 100 XP (instead of hardcoded 2*10+20=40)
    expect($result['quiz_xp_earned'])->toBe(100)
        ->and($result['total_xp_earned'])->toBe(100)
        ->and($user->fresh()->experience_points)->toBe(100);
});

test('XpService respects custom XP to coins conversion settings from BusinessSetting', function () {
    // Change ratio: every 1,000 XP converts into 40 coins (default was 1,500 XP = 50 coins)
    BusinessSetting::set('xp_to_coins_ratio_xp', 1000);
    BusinessSetting::set('xp_to_coins_ratio_coins', 40);

    $user = User::factory()->student()->create([
        'experience_points' => 0,
        'xp_converted_total' => 0,
        'coin_balance' => 0,
    ]);

    /** @var XpService $xpService */
    $xpService = app(XpService::class);
    $result = $xpService->award($user, 2500);

    // 2,500 XP gives 2 chunks of 1,000 XP -> 2 * 40 = 80 coins
    expect($result['coins_converted'])->toBe(80)
        ->and($result['coin_balance'])->toBe(80)
        ->and($user->fresh()->xp_converted_total)->toBe(2000)
        ->and($user->fresh()->coin_balance)->toBe(80);
});

test('MissionService convertXp respects custom ratio settings from BusinessSetting', function () {
    BusinessSetting::set('xp_to_coins_ratio_xp', 500);
    BusinessSetting::set('xp_to_coins_ratio_coins', 25);

    $user = User::factory()->student()->create([
        'experience_points' => 1200,
        'xp_converted_total' => 0,
        'coin_balance' => 0,
    ]);

    /** @var MissionService $missionService */
    $missionService = app(MissionService::class);
    $result = $missionService->convertXp($user);

    // 1,200 XP gives 2 chunks of 500 = 1,000 XP converted -> 2 * 25 = 50 coins
    expect($result['xp_converted'])->toBe(1000)
        ->and($result['coins_earned'])->toBe(50)
        ->and($result['remaining_unconverted_xp'])->toBe(200)
        ->and($user->fresh()->coin_balance)->toBe(50);
});

test('AdRewardService respects custom watch ad daily cap and reset hours from BusinessSetting', function () {
    // Change cap to 2 ads per 10 hours (defaults were 5 ads per 20 hours)
    BusinessSetting::set('watch_ad_daily_cap', 2);
    BusinessSetting::set('watch_ad_reset_hours', 10);

    $user = User::factory()->student()->create(['coin_balance' => 0]);

    /** @var AdRewardService $adService */
    $adService = app(AdRewardService::class);

    // 1st token
    $token1 = $adService->requestToken($user);
    expect($token1['remaining_ads'])->toBe(1);
    $adService->devSimulate($user);

    // 2nd token
    $token2 = $adService->requestToken($user);
    expect($token2['remaining_ads'])->toBe(0);
    $adService->devSimulate($user);

    // 3rd token must be rejected because cap is 2
    expect(fn () => $adService->requestToken($user))
        ->toThrow(ValidationException::class);
});

test('MissionService checkin respects custom checkin reset hours from BusinessSetting', function () {
    // Set reset hours to 12 hours (default was 20)
    BusinessSetting::set('checkin_reset_hours', 12);

    $user = User::factory()->student()->create(['coin_balance' => 0]);
    Carbon::setTestNow(now());

    /** @var MissionService $missionService */
    $missionService = app(MissionService::class);

    // Day 1 checkin
    $res1 = $missionService->checkin($user);
    expect($res1['streak_day'])->toBe(1);

    // After 13 hours (valid for 12h cooldown, but would have failed under 20h)
    Carbon::setTestNow(now()->addHours(13));
    $res2 = $missionService->checkin($user);
    expect($res2['streak_day'])->toBe(2);
});

test('MissionService referral respects custom referral cap hours from BusinessSetting', function () {
    // Set referral cap to 6 hours (default was 24)
    BusinessSetting::set('referral_cap_hours', 6);

    $referrer = User::factory()->student()->create(['referral_code' => 'REFTEST1', 'coin_balance' => 0]);
    Carbon::setTestNow(now());

    /** @var MissionService $missionService */
    $missionService = app(MissionService::class);

    // 1st referred user
    $newUser1 = User::factory()->student()->create();
    $missionService->processReferral($newUser1, 'REFTEST1');
    expect($referrer->fresh()->coin_balance)->toBe(50);

    // Advance by 7 hours (exceeds 6h cap, but under 24h)
    Carbon::setTestNow(now()->addHours(7));

    // 2nd referred user within 7 hours gets rewarded because cap is 6 hours
    $newUser2 = User::factory()->student()->create();
    $missionService->processReferral($newUser2, 'REFTEST1');
    expect($referrer->fresh()->coin_balance)->toBe(100);
});

test('ExamSessionService respects custom duration caps from BusinessSetting', function () {
    // Custom caps
    BusinessSetting::set('exam_time_cap_mcq_minutes', 45);
    BusinessSetting::set('exam_time_cap_structural_minutes', 120);
    BusinessSetting::set('exam_time_cap_mixed_minutes', 150);

    $user = User::factory()->student()->create();
    Subscription::factory()->create(['user_id' => $user->id]);

    $subject = Subject::factory()->create();
    $pastPaper = PastPaper::factory()->create(['subject_id' => $subject->id]);

    // MCQ question only
    PastPaperQuestion::factory()->create([
        'past_paper_id' => $pastPaper->id,
        'type' => PastPaperQuestionType::Mcq,
        'order' => 1,
    ]);

    /** @var ExamSessionService $examService */
    $examService = app(ExamSessionService::class);
    $sessionData = $examService->startSession($user, $pastPaper);

    expect($sessionData['session']['max_allowed_minutes'])->toBe(45)
        ->and($sessionData['session']['selected_minutes'])->toBe(45);
});

test('ChallengeService calculateCoinsForScore respects custom threshold and reward settings from BusinessSetting', function () {
    BusinessSetting::set('challenge_reward_high_threshold_percent', 85.0);
    BusinessSetting::set('challenge_reward_high_coins', 200);
    BusinessSetting::set('challenge_reward_mid_threshold_percent', 60.0);
    BusinessSetting::set('challenge_reward_mid_coins', 75);

    /** @var ChallengeService $challengeService */
    $challengeService = app(ChallengeService::class);

    // 86% qualifies for high tier (previously needed 95%)
    expect($challengeService->calculateCoinsForScore(86.0))->toBe(200);

    // 65% qualifies for mid tier (previously needed 70%)
    expect($challengeService->calculateCoinsForScore(65.0))->toBe(75);

    // 55% receives 0 coins
    expect($challengeService->calculateCoinsForScore(55.0))->toBe(0);
});

test('admin can update business settings via Filament ManageBusinessSettings page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test(ManageBusinessSettings::class)
        ->assertSuccessful()
        ->set('data.quiz_xp_per_correct_answer', 15)
        ->set('data.quiz_xp_completion_bonus', 30)
        ->set('data.watch_ad_daily_cap', 8)
        ->call('save')
        ->assertHasNoErrors();

    expect(BusinessSetting::get('quiz_xp_per_correct_answer'))->toBe(15)
        ->and(BusinessSetting::get('quiz_xp_completion_bonus'))->toBe(30)
        ->and(BusinessSetting::get('watch_ad_daily_cap'))->toBe(8);
});
