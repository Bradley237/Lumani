<?php

use App\Enums\CoinTransactionType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('user can list missions with progress and daily check-in rewards', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/missions');

    $response->assertOk()
        ->assertJsonStructure([
            'missions' => [
                '*' => [
                    'id',
                    'key',
                    'title',
                    'description',
                    'coin_reward',
                    'type',
                    'is_active',
                    'completed',
                ],
            ],
            'daily_checkin_rewards' => [
                '*' => [
                    'id',
                    'day',
                    'coin_reward',
                ],
            ],
            'user_streak',
        ]);
});

test('daily check-in awards day 1 reward on initial check-in and updates user streak and coin ledger', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin');

    $response->assertOk()
        ->assertJson([
            'streak_day' => 1,
            'coins_earned' => 3,
            'coin_balance' => 3,
        ]);

    expect($user->fresh()->coin_balance)->toBe(3);
    expect($user->fresh()->day_streak)->toBe(1);

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $user->id,
        'amount' => 3,
        'type' => CoinTransactionType::EarnedMission->value,
    ]);
});

test('daily check-in rejects a second check-in within the 20-hour window', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    // First check-in
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin')->assertOk();

    // Second check-in immediately after
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['checkin']);

    expect($user->fresh()->coin_balance)->toBe(3);
});

test('daily check-in increments streak day when checked in after 20 hours and within 40 hours', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    // Day 1
    Carbon::setTestNow(now());
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin')->assertOk();

    // Advance time by 21 hours (valid window for Day 2)
    Carbon::setTestNow(now()->addHours(21));
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin');

    $response->assertOk()
        ->assertJson([
            'streak_day' => 2,
            'coins_earned' => 5,
            'coin_balance' => 8,
        ]);

    expect($user->fresh()->coin_balance)->toBe(8);
    expect($user->fresh()->day_streak)->toBe(2);

    // Advance time by another 20 hours (valid window for Day 3)
    Carbon::setTestNow(now()->addHours(20));
    $response3 = $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin');

    $response3->assertOk()
        ->assertJson([
            'streak_day' => 3,
            'coins_earned' => 7,
            'coin_balance' => 15,
        ]);

    expect($user->fresh()->coin_balance)->toBe(15);
    expect($user->fresh()->day_streak)->toBe(3);
});

test('daily check-in resets streak to day 1 if more than 40 hours have elapsed (skipped day)', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    // Day 1
    Carbon::setTestNow(now());
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin')->assertOk();

    // Day 2 (after 21h)
    Carbon::setTestNow(now()->addHours(21));
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin')->assertOk();

    // Missed a day -> advance time by 45 hours
    Carbon::setTestNow(now()->addHours(45));
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin');

    $response->assertOk()
        ->assertJson([
            'streak_day' => 1,
            'coins_earned' => 3,
        ]);

    expect($user->fresh()->day_streak)->toBe(1);
});

test('watch ad awards 5 coins per call and caps at 5 per rolling 20-hour window', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    Carbon::setTestNow(now());

    // Watch 5 ads via request-token + dev simulation
    for ($i = 1; $i <= 5; $i++) {
        $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
        $tokenRes->assertOk()
            ->assertJsonStructure(['token', 'status', 'remaining_ads', 'expires_at'])
            ->assertJson([
                'status' => 'pending',
                'remaining_ads' => 5 - $i,
            ]);

        $simRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward');
        $simRes->assertOk()
            ->assertJson([
                'coins_earned' => 5,
                'coin_balance' => $i * 5,
            ]);
    }

    expect($user->fresh()->coin_balance)->toBe(25);

    // 6th ad token request within 20h window is rejected
    $response6 = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $response6->assertStatus(422)
        ->assertJsonValidationErrors(['ad']);

    expect($user->fresh()->coin_balance)->toBe(25);

    // Advance time by 21 hours (rolling window clears old ads)
    Carbon::setTestNow(now()->addHours(21));
    $response7 = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $response7->assertOk()
        ->assertJson([
            'remaining_ads' => 4,
        ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward')->assertOk();

    expect($user->fresh()->coin_balance)->toBe(30);
});

test('one-time missions award coins and cannot be completed twice', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    // Complete profile (30 coins)
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/missions/complete/complete_profile');
    $response->assertOk()
        ->assertJson([
            'mission' => 'complete_profile',
            'coins_earned' => 30,
            'coin_balance' => 30,
        ]);

    expect($user->fresh()->coin_balance)->toBe(30);

    // Duplicate attempt rejected
    $duplicate = $this->actingAs($user, 'sanctum')->postJson('/api/missions/complete/complete_profile');
    $duplicate->assertStatus(422)
        ->assertJsonValidationErrors(['mission']);

    expect($user->fresh()->coin_balance)->toBe(30);

    // Take first quiz (40 coins)
    $quizResp = $this->actingAs($user, 'sanctum')->postJson('/api/missions/complete/first_quiz');
    $quizResp->assertOk()
        ->assertJson([
            'mission' => 'first_quiz',
            'coins_earned' => 40,
            'coin_balance' => 70,
        ]);

    expect($user->fresh()->coin_balance)->toBe(70);

    // Duplicate first quiz rejected
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/complete/first_quiz')
        ->assertStatus(422);
});
