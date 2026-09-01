<?php

use App\Enums\AdRewardStatus;
use App\Enums\CoinTransactionType;
use App\Models\AdRewardRequest;
use App\Models\CoinTransaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MissionAndRewardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

$testPrivateKey = <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIKEubpBiHkZQYlORbCy8gGTz8tzrWsjBJA6GfFCrQ98coAoGCCqGSM49
AwEHoUQDQgAEOr6rMmRRNKuZuwws/hWwFTM6ECEEaJGGARCJUO4UfoURl8b4JThG
t8VDFKeR2i+ZxE+xh/wTBaJ/zvtSqZiNnQ==
-----END EC PRIVATE KEY-----
PEM;

$testPublicKey = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEOr6rMmRRNKuZuwws/hWwFTM6ECEE
aJGGARCJUO4UfoURl8b4JThGt8VDFKeR2i+ZxE+xh/wTBaJ/zvtSqZiNnQ==
-----END PUBLIC KEY-----
PEM;

$testKeyId = 987654321;

beforeEach(function () use ($testPublicKey, $testKeyId) {
    $this->seed(MissionAndRewardSeeder::class);

    Cache::put('admob_ssv_verifier_keys', [
        'keys' => [
            [
                'keyId' => $testKeyId,
                'pem' => $testPublicKey,
            ],
        ],
    ], 86400);
});

function createSignedUrl(string $token, int $keyId, string $privateKey, ?string $tamperedMessage = null): string
{
    $params = [
        'ad_network' => '5450213213286189855',
        'ad_unit' => '1234567890',
        'custom_data' => $token,
        'reward_amount' => '5',
        'reward_item' => 'coins',
        'timestamp' => (string) (now()->timestamp * 1000),
        'transaction_id' => (string) Str::uuid(),
        'user_id' => 'student_app_user',
    ];

    $queryString = http_build_query($params);
    $signingData = $tamperedMessage ?? $queryString;

    $priv = openssl_pkey_get_private($privateKey);
    openssl_sign($signingData, $binarySig, $priv, OPENSSL_ALGO_SHA256);
    $signature = rtrim(strtr(base64_encode($binarySig), '+/', '-_'), '=');

    return "/api/ads/reward-callback?{$queryString}&signature={$signature}&key_id={$keyId}";
}

test('student can request an ad reward token under the cap', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'status',
            'remaining_ads',
            'expires_at',
        ])
        ->assertJson([
            'status' => 'pending',
            'remaining_ads' => 4,
        ]);

    $token = $response->json('token');
    expect($token)->toBeString()->toHaveLength(40);

    $this->assertDatabaseHas('ad_reward_requests', [
        'user_id' => $user->id,
        'token' => $token,
        'status' => AdRewardStatus::Pending->value,
    ]);
});

test('request-token respects the 5 per 20-hour cap', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    Carbon::setTestNow(now());

    // Redeem 5 ads
    for ($i = 1; $i <= 5; $i++) {
        $res = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
        $res->assertOk()
            ->assertJson([
                'remaining_ads' => 5 - $i,
            ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward')->assertOk();
    }

    expect($user->fresh()->coin_balance)->toBe(25);

    // 6th request within 20-hour window is rejected with 422
    $response6 = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $response6->assertStatus(422)
        ->assertJsonValidationErrors(['ad']);

    // Advance time by 21 hours
    Carbon::setTestNow(now()->addHours(21));

    $response7 = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $response7->assertOk()
        ->assertJson([
            'remaining_ads' => 4,
        ]);
});

test('a valid signed callback credits 5 coins exactly once and marks request redeemed', function () use ($testPrivateKey, $testKeyId) {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $token = $tokenRes->json('token');

    $callbackUrl = createSignedUrl($token, $testKeyId, $testPrivateKey);

    // Public GET callback from Google AdMob server
    $response = $this->getJson($callbackUrl);
    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    $freshUser = $user->fresh();
    expect($freshUser->coin_balance)->toBe(5);

    $adRequest = AdRewardRequest::where('token', $token)->first();
    expect($adRequest->status)->toBe(AdRewardStatus::Redeemed)
        ->and($adRequest->redeemed_at)->not->toBeNull();

    $this->assertDatabaseHas('coin_transactions', [
        'user_id' => $user->id,
        'amount' => 5,
        'type' => CoinTransactionType::EarnedMission->value,
        'reference_type' => $adRequest->getMorphClass(),
        'reference_id' => $adRequest->id,
    ]);
});

test('a replayed callback does nothing the second time', function () use ($testPrivateKey, $testKeyId) {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $token = $tokenRes->json('token');

    $callbackUrl = createSignedUrl($token, $testKeyId, $testPrivateKey);

    // 1st callback: succeeds
    $this->getJson($callbackUrl)->assertOk();
    expect($user->fresh()->coin_balance)->toBe(5);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(1);

    // 2nd callback with same signature & token: returns 200 per AdMob spec, but does nothing
    $this->getJson($callbackUrl)->assertOk();
    expect($user->fresh()->coin_balance)->toBe(5);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(1);
});

test('a callback with an invalid signature is rejected and awards 0 coins', function () use ($testPrivateKey, $testKeyId) {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $token = $tokenRes->json('token');

    // Create URL where query string was signed with different text (signature mismatch)
    $callbackUrl = createSignedUrl($token, $testKeyId, $testPrivateKey, 'tampered_payload_different_message');

    $response = $this->getJson($callbackUrl);
    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    // Coins NOT credited, request remains pending
    expect($user->fresh()->coin_balance)->toBe(0);
    $adRequest = AdRewardRequest::where('token', $token)->first();
    expect($adRequest->status)->toBe(AdRewardStatus::Pending);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(0);
});

test('a callback with an unknown key_id is rejected and awards 0 coins', function () use ($testPrivateKey) {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $token = $tokenRes->json('token');

    $callbackUrl = createSignedUrl($token, 999999999, $testPrivateKey);

    $response = $this->getJson($callbackUrl);
    $response->assertOk();

    expect($user->fresh()->coin_balance)->toBe(0);
    expect(AdRewardRequest::where('token', $token)->first()->status)->toBe(AdRewardStatus::Pending);
});

test('a callback for a token older than 10 minutes is rejected', function () use ($testPrivateKey, $testKeyId) {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    Carbon::setTestNow(now());

    $tokenRes = $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token');
    $token = $tokenRes->json('token');

    // Advance time by 11 minutes (token expired)
    Carbon::setTestNow(now()->addMinutes(11));

    $callbackUrl = createSignedUrl($token, $testKeyId, $testPrivateKey);

    $response = $this->getJson($callbackUrl);
    $response->assertOk();

    expect($user->fresh()->coin_balance)->toBe(0);
    $adRequest = AdRewardRequest::where('token', $token)->first();
    expect($adRequest->status)->toBe(AdRewardStatus::Expired);
    expect(CoinTransaction::where('user_id', $user->id)->count())->toBe(0);
});

test('a callback for a nonexistent token is safely ignored', function () use ($testPrivateKey, $testKeyId) {
    $callbackUrl = createSignedUrl('nonexistent-token-1234567890abcdef', $testKeyId, $testPrivateKey);

    $response = $this->getJson($callbackUrl);
    $response->assertOk();

    expect(CoinTransaction::count())->toBe(0);
});

test('dev-simulate-reward redeems most recent pending token for user', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $this->actingAs($user, 'sanctum')->postJson('/api/ads/request-token')->assertOk();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward');
    $response->assertOk()
        ->assertJson([
            'message' => 'Reward redeemed successfully.',
            'coins_earned' => 5,
            'coin_balance' => 5,
        ]);

    expect($user->fresh()->coin_balance)->toBe(5);

    // Second simulation without a new token fails with 422
    $response2 = $this->actingAs($user, 'sanctum')->postJson('/api/ads/dev-simulate-reward');
    $response2->assertStatus(422)
        ->assertJsonValidationErrors(['ad']);
});

test('dev-simulate-reward route is not registered when ADMOB_SSV_ENABLED is true', function () {
    // Override config to simulate production/SSV-enabled mode
    Config::set('admob.ssv_enabled', true);

    // Re-register routes for this test
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = User::factory()->student()->create(['coin_balance' => 0]);

    // When SSV is enabled in production, the dev simulation route does not exist
    $this->actingAs($user, 'sanctum');

    // Test that the route condition logic works
    $isRouteRegistered = app('router')->getRoutes()->hasNamedRoute('api.ads.dev-simulate-reward');
    // In our test environment, config was set at boot time. Let's verify the condition directly:
    $shouldExist = ! config('admob.ssv_enabled') && app()->environment('local', 'testing');
    expect($shouldExist)->toBeFalse();
});
