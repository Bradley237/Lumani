<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('user can register successfully via mobile api and receives sanctum token', function () {
    $payload = [
        'first_name' => 'Jean',
        'last_name' => 'Fomekong',
        'email' => 'jean.fomekong@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'preferred_language' => 'fr',
        'phone_number' => '+237670000000',
    ];

    $response = $this->postJson(route('api.register'), $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'token',
            'token_type',
            'user' => [
                'id',
                'first_name',
                'last_name',
                'email',
                'role',
                'preferred_language',
                'phone_number',
                'coin_balance',
                'experience_points',
                'day_streak',
            ],
        ])
        ->assertJsonPath('user.first_name', 'Jean')
        ->assertJsonPath('user.last_name', 'Fomekong')
        ->assertJsonPath('user.email', 'jean.fomekong@example.com')
        ->assertJsonPath('user.role', UserRole::Student->value)
        ->assertJsonPath('user.preferred_language', 'fr')
        ->assertJsonPath('user.phone_number', '+237670000000')
        ->assertJsonPath('user.coin_balance', 0)
        ->assertJsonPath('user.experience_points', 0)
        ->assertJsonPath('user.day_streak', 0);

    $token = $response->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    // Verify token authenticates protected route
    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('user.email', 'jean.fomekong@example.com');
});

test('registration rejects duplicate email', function () {
    User::factory()->create([
        'email' => 'duplicate@example.com',
    ]);

    $response = $this->postJson(route('api.register'), [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'duplicate@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('registration validates required fields and password confirmation', function () {
    $response = $this->postJson(route('api.register'), [
        'first_name' => '',
        'last_name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
});

test('user can log in successfully with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => 'SecretPassword123',
    ]);

    $response = $this->postJson(route('api.login'), [
        'email' => 'student@example.com',
        'password' => 'SecretPassword123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'token',
            'token_type',
            'user' => [
                'id',
                'first_name',
                'last_name',
                'email',
                'role',
            ],
        ])
        ->assertJsonPath('user.email', 'student@example.com');

    $token = $response->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

test('login rejects incorrect password', function () {
    User::factory()->create([
        'email' => 'student@example.com',
        'password' => 'CorrectPassword',
    ]);

    $response = $this->postJson(route('api.login'), [
        'email' => 'student@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

test('login rejects non-existent email', function () {
    $response = $this->postJson(route('api.login'), [
        'email' => 'doesnotexist@example.com',
        'password' => 'AnyPassword',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

test('logout revokes current sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile-app')->plainTextToken;

    expect(PersonalAccessToken::count())->toBe(1);

    $response = $this->withToken($token)
        ->postJson(route('api.logout'));

    $response->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect(PersonalAccessToken::count())->toBe(0);

    // Using the revoked token should now fail
    app('auth')->forgetGuards();
    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized();
});

test('api user endpoint requires authentication', function () {
    $this->getJson(route('api.user'))
        ->assertUnauthorized();
});
