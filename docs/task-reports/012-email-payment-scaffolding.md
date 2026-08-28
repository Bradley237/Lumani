# Task 012 — Email & Payment Scaffolding (Provider-Agnostic)

## Overview & Status
- **Status**: Completed & Fully Verified
- **Scope**: Email communication scaffolding using Laravel's native Mail system and a swappable payment abstraction for subscription purchasing.
- **Key Invariants**:
  - Provider-agnostic design: zero business logic coupled to specific email or payment vendors.
  - Emails use `MAIL_MAILER=log` in local/staging environments, writing output to `storage/logs/laravel.log`.
  - `PaymentGatewayContract` interface provides uniform payment initialization and webhook callback handling.
  - Subscriptions extend existing active periods (stacked from `end_date`) and automatically credit coin allotments (500 for `tier_2000`, 1500 for `tier_5000`) using `CoinService`.
  - Webhooks/callbacks are idempotent and fail safely without granting unauthorized subscriptions.

---

## 1. What Was Built

### 1.1 Part A: Email Architecture
1. **Mail Configuration**:
   - `MAIL_MAILER=log` configured in `.env` and `.env.example`.
2. **Mailable Classes & HTML Views**:
   - **`App\Mail\WelcomeEmail`** (`resources/views/emails/welcome.blade.php`):
     - Welcomes newly registered students.
     - Outlines key features: Chapter Quizzes, Timed Exam Mode, and AI Tutor.
     - Highlights the student's unique `referral_code` for sharing.
     - Dispatched synchronously upon mobile registration (`AuthController@register`) and web registration (`CreateNewUser@create`).
   - **`App\Mail\StreakReminderEmail`** (`resources/views/emails/streak-reminder.blade.php`):
     - Alerts students when their learning streak is at risk of resetting to zero.
     - Includes dynamic streak count display (e.g. `🔥 7 Days`) and call-to-action link.
     - Built and ready for automated triggering when a scheduled cron/worker job is added.
   - **`App\Mail\AdminNewSubmissionEmail`** (`resources/views/emails/admin-new-submission.blade.php`):
     - Dispatched automatically to all administrator users (`role === UserRole::Admin`) whenever a new `SubmittedQuestion` record is created (via `SubmittedQuestion::booted` lifecycle hook).
     - Contains submission ID, chapter title, question snippet, submitter email, and a direct review link to `/admin/submitted-questions/{id}/edit`.

### 1.2 Part B: Payment & Subscription Scaffolding
1. **Interface Contract (`App\Contracts\PaymentGatewayContract`)**:
   ```php
   public function initiate(User $user, string $tier): array;
   public function handleCallback(array $payload): bool;
   ```
2. **Service Layer (`App\Services\SubscriptionService`)**:
   - `grantSubscription(User $user, SubscriptionTier $tier, int $durationMonths = 1): Subscription`:
     - Checks if user has an active subscription; if active, starts the new subscription period seamlessly from the current `end_date` (stacking).
     - Inserts the `Subscription` record (`status = active`, amount in FCFA, start/end dates).
     - Credits the tier's coin allotment via `CoinService->award()` using `CoinTransactionType::EarnedSubscription` with polymorphic reference.
3. **Mock Gateway (`App\Services\Payment\MockPaymentGateway`)**:
   - Implements `PaymentGatewayContract`.
   - `initiate()`: Validates tier, generates `mock_pay_...` reference, logs intent, caches the checkout intent, and returns checkout metadata.
   - `handleCallback()`: Parses webhook payload (or cached reference intent), validates status, resolves user and tier, calls `SubscriptionService::grantSubscription()`, and returns a boolean.
4. **Service Container Binding (`App\Providers\AppServiceProvider`)**:
   - Binds `PaymentGatewayContract::class => MockPaymentGateway::class`.
5. **API Endpoints**:
   - `POST /api/subscriptions/purchase` (Auth: Sanctum):
     - Request body: `{"tier": "tier_2000" | "tier_5000"}`
     - Calls `PaymentGatewayContract::initiate()`.
     - Returns `200 OK` with payment reference and checkout URL.
   - `POST /api/payments/callback`:
     - Public webhook/callback endpoint for payment gateways.
     - Calls `PaymentGatewayContract::handleCallback($request->all())`.
     - Returns `200 OK` on success, `400 Bad Request` on failure/invalid payload.

---

## 2. Exactly What Changes When Real Providers Are Chosen

### 2.1 Switching to a Real Email Provider (e.g. Resend, Postmark, Mailgun, Amazon SES, SMTP)
**Zero application code changes required.** Only `.env` configuration changes:

```env
# Example: Production SMTP (e.g., Postmark / Brevo / Custom SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-token
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="notifications@lumani.cm"
MAIL_FROM_NAME="Lumani Education"

# OR Example: Resend via API driver
MAIL_MAILER=resend
RESEND_KEY=re_123456789
```

All Mailables (`WelcomeEmail`, `StreakReminderEmail`, `AdminNewSubmissionEmail`) automatically route through the configured transport.

### 2.2 Switching to a Real Payment Gateway (e.g. Campay, Notch Pay, MTN/Orange Mobile Money)
1. **Create the Concrete Gateway Class**:
   - Create `app/Services/Payment/CampayPaymentGateway.php` (or `NotchPayGateway.php`) implementing `App\Contracts\PaymentGatewayContract`.
   - `initiate()`: Makes HTTP request to the provider's API (e.g. `https://demo.campay.net/api/get_payment_token/` or Notch Pay checkout initialize) and returns real payment reference & payment link.
   - `handleCallback()`: Verifies webhook signature/HMAC, extracts transaction reference & status, and calls `SubscriptionService::grantSubscription($user, $tier)`.
2. **Update the Service Provider Binding**:
   - In `app/Providers/AppServiceProvider.php` (or a `PaymentServiceProvider`):
     ```php
     // One line change:
     $this->app->bind(PaymentGatewayContract::class, CampayPaymentGateway::class);
     ```
3. **Configure Gateway Credentials in `.env`**:
   ```env
   CAMPAY_USERNAME=your_username
   CAMPAY_PASSWORD=your_password
   CAMPAY_APP_TOKEN=your_token
   CAMPAY_ENVIRONMENT=production # or demo
   ```

---

## 3. Test Coverage & Verification

- **Feature Tests (`tests/Feature/EmailAndPaymentScaffoldingTest.php`)**:
  - `welcome email is sent synchronously on user api registration with referral code`
  - `admin new submission email is sent to all admins when a submitted question is created`
  - `streak reminder email can be instantiated and renders streak count`
  - `payment gateway contract is bound to MockPaymentGateway in service container`
  - `mock payment gateway initiate generates valid payment reference and checkout info`
  - `mock payment gateway handleCallback with success grants subscription and coin allotment`
  - `mock payment gateway handleCallback extending active subscription adds time from end_date`
  - `mock payment gateway handleCallback fails cleanly on failure status and grants nothing`
  - `authenticated user can initiate subscription purchase via api endpoint`
  - `payments callback api endpoint handles successful webhook`
  - `payments callback api endpoint returns 400 on failed webhook`

- **Full Test Suite**: 160 tests, 875 assertions passed (0 failures).
- **Code Style**: 100% formatted and verified via Laravel Pint.

---

## 4. Open Questions & Next Steps

1. **Streak Reminder Scheduling**: When a scheduler (e.g., Laravel Scheduler via cron or queue workers) is deployed, configure a daily job (e.g., at 18:00 UTC+1) that checks students whose streak is at risk and sends `StreakReminderEmail`.
2. **Cameroon Payment Provider Selection**: Evaluate Campay vs. Notch Pay vs. direct Mobile Money operators (MTN MoMo Cameroon & Orange Money) based on transaction fees and reliability for recurring or one-time mobile subscriptions.
