<?php

use App\Contracts\PaymentGatewayContract;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Mail\AdminNewSubmissionEmail;
use App\Mail\StreakReminderEmail;
use App\Mail\WelcomeEmail;
use App\Models\Chapter;
use App\Models\SubmittedQuestion;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('welcome email is sent synchronously on user api registration with referral code', function () {
    Mail::fake();

    $response = $this->postJson('/api/register', [
        'first_name' => 'Jean',
        'last_name' => 'Paul',
        'email' => 'jean.paul@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'preferred_language' => 'en',
    ]);

    $response->assertCreated();

    $user = User::where('email', 'jean.paul@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->referral_code)->not->toBeEmpty();

    Mail::assertSent(WelcomeEmail::class, function (WelcomeEmail $mail) use ($user) {
        return $mail->hasTo('jean.paul@example.com')
            && $mail->user->id === $user->id
            && $mail->user->referral_code === $user->referral_code;
    });
});

test('admin new submission email is sent to all admins when a submitted question is created', function () {
    Mail::fake();

    $admin1 = User::factory()->admin()->create(['email' => 'admin1@lumani.cm']);
    $admin2 = User::factory()->admin()->create(['email' => 'admin2@lumani.cm']);
    $student = User::factory()->student()->create(['email' => 'student@lumani.cm']);

    $chapter = Chapter::factory()->create();

    $submission = SubmittedQuestion::create([
        'submitted_by' => $student->id,
        'chapter_id' => $chapter->id,
        'question_text' => 'What is Newton second law?',
        'answer_choices' => ['A' => 'F = ma', 'B' => 'E = mc^2'],
        'correct_choice' => 'A',
    ]);

    Mail::assertSent(AdminNewSubmissionEmail::class, 2);

    Mail::assertSent(AdminNewSubmissionEmail::class, function (AdminNewSubmissionEmail $mail) use ($submission) {
        return $mail->hasTo('admin1@lumani.cm')
            && $mail->submittedQuestion->id === $submission->id;
    });

    Mail::assertSent(AdminNewSubmissionEmail::class, function (AdminNewSubmissionEmail $mail) use ($submission) {
        return $mail->hasTo('admin2@lumani.cm')
            && $mail->submittedQuestion->id === $submission->id;
    });

    Mail::assertNotSent(AdminNewSubmissionEmail::class, function (AdminNewSubmissionEmail $mail) {
        return $mail->hasTo('student@lumani.cm');
    });
});

test('streak reminder email can be instantiated and renders streak count', function () {
    $student = User::factory()->student()->create([
        'first_name' => 'Marie',
        'day_streak' => 7,
    ]);

    $mailable = new StreakReminderEmail($student);

    $mailable->assertSeeInHtml('Marie');
    $mailable->assertSeeInHtml('7 Days');
    $mailable->assertHasSubject('Keep your streak alive on Lumani! 🔥');
});

test('payment gateway contract is bound to MockPaymentGateway in service container', function () {
    $gateway = app(PaymentGatewayContract::class);

    expect($gateway)->toBeInstanceOf(MockPaymentGateway::class);
});

test('mock payment gateway initiate generates valid payment reference and checkout info', function () {
    $user = User::factory()->student()->create();
    $gateway = app(PaymentGatewayContract::class);

    $checkout = $gateway->initiate($user, 'tier_2000');

    expect($checkout)->toHaveKeys([
        'payment_reference',
        'checkout_url',
        'tier',
        'amount_fcfa',
        'coin_allotment',
        'status',
    ]);
    expect($checkout['payment_reference'])->toStartWith('mock_pay_');
    expect($checkout['tier'])->toBe('tier_2000');
    expect($checkout['amount_fcfa'])->toBe(2000);
    expect($checkout['coin_allotment'])->toBe(500);
    expect($checkout['status'])->toBe('pending');
});

test('mock payment gateway handleCallback with success grants subscription and coin allotment', function () {
    $user = User::factory()->student()->create(['coin_balance' => 100]);
    $gateway = app(PaymentGatewayContract::class);

    $initData = $gateway->initiate($user, 'tier_5000');
    $ref = $initData['payment_reference'];

    $success = $gateway->handleCallback([
        'payment_reference' => $ref,
        'status' => 'success',
    ]);

    expect($success)->toBeTrue();

    $subscription = Subscription::where('user_id', $user->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->tier)->toBe(SubscriptionTier::Tier5000);
    expect($subscription->amount_fcfa)->toBe(5000);
    expect($subscription->coin_allotment)->toBe(1500);
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->end_date->isFuture())->toBeTrue();

    expect($user->fresh()->coin_balance)->toBe(1600); // 100 initial + 1500 allotment
});

test('mock payment gateway handleCallback extending active subscription adds time from end_date', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);
    $gateway = app(PaymentGatewayContract::class);

    // Initial 1 month subscription
    $gateway->handleCallback([
        'user_id' => $user->id,
        'tier' => 'tier_2000',
        'status' => 'success',
    ]);

    $firstSub = Subscription::where('user_id', $user->id)->first();
    expect($firstSub)->not->toBeNull();
    $firstEnd = $firstSub->end_date;

    // Second purchase extending active subscription
    $gateway->handleCallback([
        'user_id' => $user->id,
        'tier' => 'tier_5000',
        'status' => 'success',
    ]);

    expect(Subscription::where('user_id', $user->id)->count())->toBe(2);
    $secondSub = Subscription::where('user_id', $user->id)->latest('id')->first();

    expect($secondSub->start_date->toIso8601String())->toBe($firstEnd->toIso8601String());
    expect($secondSub->end_date->greaterThan($firstEnd))->toBeTrue();
    expect($user->fresh()->coin_balance)->toBe(2000); // 500 + 1500
});

test('mock payment gateway handleCallback fails cleanly on failure status and grants nothing', function () {
    $user = User::factory()->student()->create(['coin_balance' => 50]);
    $gateway = app(PaymentGatewayContract::class);

    $initData = $gateway->initiate($user, 'tier_2000');

    $result = $gateway->handleCallback([
        'payment_reference' => $initData['payment_reference'],
        'status' => 'failed',
    ]);

    expect($result)->toBeFalse();
    expect(Subscription::where('user_id', $user->id)->count())->toBe(0);
    expect($user->fresh()->coin_balance)->toBe(50);
});

test('authenticated user can initiate subscription purchase via api endpoint', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user)->postJson('/api/subscriptions/purchase', [
        'tier' => 'tier_5000',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'payment_reference',
                'checkout_url',
                'tier',
                'amount_fcfa',
                'coin_allotment',
                'status',
            ],
        ]);

    expect($response->json('data.tier'))->toBe('tier_5000');
    expect($response->json('data.amount_fcfa'))->toBe(5000);
});

test('payments callback api endpoint handles successful webhook', function () {
    $user = User::factory()->student()->create(['coin_balance' => 0]);

    $response = $this->postJson('/api/payments/callback', [
        'user_id' => $user->id,
        'tier' => 'tier_2000',
        'status' => 'success',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Payment processed successfully.',
        ]);

    expect(Subscription::where('user_id', $user->id)->exists())->toBeTrue();
    expect($user->fresh()->coin_balance)->toBe(500);
});

test('payments callback api endpoint returns 400 on failed webhook', function () {
    $response = $this->postJson('/api/payments/callback', [
        'status' => 'failed',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
        ]);
});
