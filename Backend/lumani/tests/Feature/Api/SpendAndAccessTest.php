<?php

use App\Enums\CoinTransactionType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\AppSetting;
use App\Models\Chapter;
use App\Models\CoinTransaction;
use App\Models\PastPaper;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserChapterUnlock;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MissionAndRewardSeeder::class);
});

test('free mode bypasses all charges for chapters and past papers even at 0 balance', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 0]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'is_free' => false,
    ]);
    $pastPaper = PastPaper::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 15,
        'solution_coin_price' => 20,
    ]);

    // Unlock chapter with 0 balance
    $resp1 = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock");
    $resp1->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 0,
        ]);

    // Unlock past paper with 0 balance
    $resp2 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-paper");
    $resp2->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 0,
        ]);

    // Unlock past paper solution with 0 balance
    $resp3 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-solution");
    $resp3->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 0,
        ]);

    expect($user->fresh()->coin_balance)->toBe(0);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(0);
});

test('is_free chapters are accessible at 0 balance even when free_mode is off', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 0]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->free()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 0,
        ]);

    expect($user->fresh()->coin_balance)->toBe(0);
});

test('insufficient balance is rejected with 422 and charges nothing', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 20]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'is_free' => false,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['coins']);

    expect($user->fresh()->coin_balance)->toBe(20);
    expect(UserChapterUnlock::where('user_id', $user->id)->count())->toBe(0);
});

test('sufficient balance deducts correctly and creates unlock record', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 100]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'is_free' => false,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => false,
            'coins_spent' => 50,
            'coin_balance' => 50,
        ]);

    expect($user->fresh()->coin_balance)->toBe(50);

    $this->assertDatabaseHas('user_chapter_unlocks', [
        'user_id' => $user->id,
        'chapter_id' => $chapter->id,
    ]);

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $user->id,
        'amount' => -50,
        'type' => CoinTransactionType::SpentUnlock->value,
        'reference_type' => Chapter::class,
        'reference_id' => $chapter->id,
    ]);
});

test('unlocking an already unlocked chapter does not charge a second time', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 100]);
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 50,
        'is_free' => false,
    ]);

    // First unlock (-50 coins)
    $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock")->assertOk();
    expect($user->fresh()->coin_balance)->toBe(50);

    // Second unlock attempt (idempotent, 0 coins charged)
    $secondResp = $this->actingAs($user, 'sanctum')->postJson("/api/chapters/{$chapter->id}/unlock");
    $secondResp->assertOk()
        ->assertJson([
            'success' => true,
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 50,
        ]);

    expect($user->fresh()->coin_balance)->toBe(50);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(1);
});

test('past paper and solution unlocks charge separately and do not double charge', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $user = User::factory()->student()->create(['coin_balance' => 50]);
    $subject = Subject::factory()->create();
    $pastPaper = PastPaper::factory()->create([
        'subject_id' => $subject->id,
        'coin_price' => 15,
        'solution_coin_price' => 20,
    ]);

    // Unlock paper (-15)
    $resp1 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-paper");
    $resp1->assertOk()
        ->assertJson([
            'coins_spent' => 15,
            'coin_balance' => 35,
        ]);
    expect($user->fresh()->coin_balance)->toBe(35);

    // Unlock solution (-20)
    $resp2 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-solution");
    $resp2->assertOk()
        ->assertJson([
            'coins_spent' => 20,
            'coin_balance' => 15,
        ]);
    expect($user->fresh()->coin_balance)->toBe(15);

    // Re-unlocking solution does not charge again
    $resp3 = $this->actingAs($user, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-solution");
    $resp3->assertOk()
        ->assertJson([
            'already_unlocked' => true,
            'coins_spent' => 0,
            'coin_balance' => 15,
        ]);
    expect($user->fresh()->coin_balance)->toBe(15);
});

test('hasActiveSubscription reflects admin granted subscription date range and expires correctly', function () {
    $user = User::factory()->student()->create();

    // No subscription initially
    $resp1 = $this->actingAs($user, 'sanctum')->getJson('/api/subscription');
    $resp1->assertOk()
        ->assertJson([
            'has_active_subscription' => false,
            'free_mode_enabled' => false,
            'subscription' => null,
        ]);

    // Active subscription
    $sub = Subscription::factory()->create([
        'user_id' => $user->id,
        'tier' => SubscriptionTier::Tier2000,
        'coin_allotment' => 500,
        'amount_fcfa' => 2000,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(29),
        'status' => SubscriptionStatus::Active,
    ]);

    $resp2 = $this->actingAs($user, 'sanctum')->getJson('/api/subscription');
    $resp2->assertOk()
        ->assertJson([
            'has_active_subscription' => true,
            'free_mode_enabled' => false,
            'subscription' => [
                'id' => $sub->id,
                'tier' => 'tier_2000',
                'status' => 'active',
                'coin_allotment' => 500,
                'amount_fcfa' => 2000,
            ],
        ]);

    // Expire subscription
    $sub->end_date = now()->subHour();
    $sub->save();

    $resp3 = $this->actingAs($user, 'sanctum')->getJson('/api/subscription');
    $resp3->assertOk()
        ->assertJson([
            'has_active_subscription' => false,
            'free_mode_enabled' => false,
        ]);

    // Turn on global free mode
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    $resp4 = $this->actingAs($user, 'sanctum')->getJson('/api/subscription');
    $resp4->assertOk()
        ->assertJson([
            'has_active_subscription' => true,
            'free_mode_enabled' => true,
        ]);
});
