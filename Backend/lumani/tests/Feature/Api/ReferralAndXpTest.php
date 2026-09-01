<?php

use App\Enums\CoinTransactionType;
use App\Models\CoinTransaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('referrer receives 50 coins on new user registration with valid referral code', function () {
    $referrer = User::factory()->student()->create([
        'coin_balance' => 0,
        'referral_code' => 'REFTEST1',
    ]);

    $payload = [
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => 'REFTEST1',
    ];

    $response = $this->postJson('/api/register', $payload);

    $response->assertStatus(201);

    expect($referrer->fresh()->coin_balance)->toBe(50);

    $newUser = User::where('email', 'alice@example.com')->first();
    expect($newUser->referred_by_user_id)->toBe($referrer->id);

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $referrer->id,
        'amount' => 50,
        'type' => CoinTransactionType::EarnedReferral->value,
        'reference_type' => User::class,
        'reference_id' => $newUser->id,
    ]);
});

test('referral reward respects strict 24-hour cap of max 1 reward per referrer', function () {
    $referrer = User::factory()->student()->create([
        'coin_balance' => 0,
        'referral_code' => 'REFTEST2',
    ]);

    Carbon::setTestNow(now());

    // First referral within 24h -> awards 50 coins
    $this->postJson('/api/register', [
        'first_name' => 'User1',
        'last_name' => 'Test',
        'email' => 'user1@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => 'REFTEST2',
    ])->assertStatus(201);

    expect($referrer->fresh()->coin_balance)->toBe(50);

    // Second referral within 24h -> linked, but referrer is capped (no extra 50 coins)
    $this->postJson('/api/register', [
        'first_name' => 'User2',
        'last_name' => 'Test',
        'email' => 'user2@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => 'REFTEST2',
    ])->assertStatus(201);

    expect($referrer->fresh()->coin_balance)->toBe(50);
    $user2 = User::where('email', 'user2@example.com')->first();
    expect($user2->referred_by_user_id)->toBe($referrer->id);

    // Advance time past 24 hours -> third referral awards 50 coins again
    Carbon::setTestNow(now()->addHours(25));

    $this->postJson('/api/register', [
        'first_name' => 'User3',
        'last_name' => 'Test',
        'email' => 'user3@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => 'REFTEST2',
    ])->assertStatus(201);

    expect($referrer->fresh()->coin_balance)->toBe(100);
});

test('user can fetch referral code and referral statistics', function () {
    $user = User::factory()->student()->create([
        'coin_balance' => 50,
        'referral_code' => 'MYCODE99',
    ]);

    $refUser = User::factory()->student()->create([
        'referred_by_user_id' => $user->id,
    ]);

    CoinTransaction::factory()->create([
        'user_id' => $user->id,
        'amount' => 50,
        'type' => CoinTransactionType::EarnedReferral,
        'reference_type' => User::class,
        'reference_id' => $refUser->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/referral-code');

    $response->assertOk()
        ->assertJson([
            'referral_code' => 'MYCODE99',
            'total_referrals' => 1,
            'coins_earned_from_referrals' => 50,
        ]);
});

test('xp conversion converts only whole 1500-unit chunks into 50 coins each', function () {
    $user = User::factory()->student()->create([
        'coin_balance' => 0,
        'experience_points' => 3200, // 2 full chunks (3000 XP) + 200 XP remainder
        'xp_converted_total' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/xp/convert');

    $response->assertOk()
        ->assertJson([
            'xp_converted' => 3000,
            'coins_earned' => 100,
            'xp_converted_total' => 3000,
            'experience_points' => 3200, // untouched lifetime XP!
            'remaining_unconverted_xp' => 200,
            'coin_balance' => 100,
        ]);

    $freshUser = $user->fresh();
    expect($freshUser->coin_balance)->toBe(100);
    expect($freshUser->experience_points)->toBe(3200); // permanent lifetime stat
    expect($freshUser->xp_converted_total)->toBe(3000);

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $user->id,
        'amount' => 100,
        'type' => CoinTransactionType::EarnedXpConversion->value,
    ]);

    // Submitting conversion again with remaining 200 XP is rejected
    $secondAttempt = $this->actingAs($user, 'sanctum')->postJson('/api/xp/convert');
    $secondAttempt->assertStatus(422)
        ->assertJsonValidationErrors(['xp']);

    // When user earns another 1300 XP (total 4500 XP, 1500 unconverted)
    $freshUser->experience_points = 4500;
    $freshUser->save();

    $thirdAttempt = $this->actingAs($freshUser, 'sanctum')->postJson('/api/xp/convert');
    $thirdAttempt->assertOk()
        ->assertJson([
            'xp_converted' => 1500,
            'coins_earned' => 50,
            'xp_converted_total' => 4500,
            'remaining_unconverted_xp' => 0,
            'coin_balance' => 150,
        ]);

    expect($freshUser->fresh()->coin_balance)->toBe(150);
});

test('coin balance always equals sum of all coin transactions for user', function () {
    $user = User::factory()->student()->create([
        'coin_balance' => 0,
        'experience_points' => 3000,
        'xp_converted_total' => 0,
    ]);

    // 1. Daily check-in (+3)
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/checkin')->assertOk();

    // 2. Watch ad (+5 via AdMob SSV request-token + dev simulation)
    $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token')->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward')->assertOk();

    // 3. Complete profile (+30)
    $this->actingAs($user, 'sanctum')->postJson('/api/missions/complete/complete_profile')->assertOk();

    // 4. Convert XP (+100)
    $this->actingAs($user, 'sanctum')->postJson('/api/xp/convert')->assertOk();

    $freshUser = $user->fresh();
    $expectedTotal = 3 + 5 + 30 + 100; // 138

    expect($freshUser->coin_balance)->toBe($expectedTotal);

    $sumFromTransactions = (int) CoinTransaction::where('user_id', $user->id)->sum('amount');
    expect($sumFromTransactions)->toBe($expectedTotal);
    expect($freshUser->coin_balance)->toBe($sumFromTransactions);
});
