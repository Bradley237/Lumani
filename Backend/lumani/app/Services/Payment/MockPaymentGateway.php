<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Enums\SubscriptionTier;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MockPaymentGateway implements PaymentGatewayContract
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Initiate a mock payment attempt.
     */
    public function initiate(User $user, string $tier): array
    {
        $enumTier = SubscriptionTier::tryFrom($tier);

        if (! $enumTier) {
            throw ValidationException::withMessages([
                'tier' => 'Invalid subscription tier selected.',
            ]);
        }

        $reference = 'mock_pay_'.Str::random(16);

        Log::info('Mock payment initiated', [
            'user_id' => $user->id,
            'tier' => $enumTier->value,
            'reference' => $reference,
            'amount_fcfa' => $enumTier->priceFcfa(),
        ]);

        Cache::put("payment_ref:{$reference}", [
            'user_id' => $user->id,
            'tier' => $enumTier->value,
            'amount_fcfa' => $enumTier->priceFcfa(),
        ], now()->addHours(2));

        return [
            'payment_reference' => $reference,
            'checkout_url' => url("/api/mock-checkout/{$reference}"),
            'tier' => $enumTier->value,
            'amount_fcfa' => $enumTier->priceFcfa(),
            'coin_allotment' => $enumTier->coinAllotment(),
            'status' => 'pending',
        ];
    }

    /**
     * Process payment webhook / callback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): bool
    {
        Log::info('Mock payment callback received', ['payload' => $payload]);

        $status = strtolower((string) ($payload['status'] ?? 'success'));
        if (in_array($status, ['failed', 'cancelled', 'canceled', 'declined', 'error'], true)) {
            Log::warning('Mock payment callback reported failure status', ['status' => $status]);

            return false;
        }

        $reference = $payload['payment_reference'] ?? $payload['reference'] ?? null;
        $cachedIntent = $reference ? Cache::get("payment_ref:{$reference}") : null;

        $userId = $payload['user_id'] ?? $cachedIntent['user_id'] ?? null;
        $tierValue = $payload['tier'] ?? $cachedIntent['tier'] ?? null;

        if (! $userId || ! $tierValue) {
            Log::warning('Mock payment callback missing user or tier information', ['payload' => $payload]);

            return false;
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user) {
            Log::warning('Mock payment callback user not found', ['user_id' => $userId]);

            return false;
        }

        $tier = SubscriptionTier::tryFrom((string) $tierValue);
        if (! $tier) {
            Log::warning('Mock payment callback invalid subscription tier', ['tier' => $tierValue]);

            return false;
        }

        $this->subscriptionService->grantSubscription($user, $tier);

        if ($reference) {
            Cache::forget("payment_ref:{$reference}");
        }

        return true;
    }
}
