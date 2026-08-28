<?php

namespace App\Contracts;

use App\Models\User;

interface PaymentGatewayContract
{
    /**
     * Initiate a payment transaction for a subscription tier.
     *
     * @return array{
     *     payment_reference: string,
     *     checkout_url: string,
     *     tier: string,
     *     amount_fcfa: int,
     *     coin_allotment: int,
     *     status: string
     * }
     */
    public function initiate(User $user, string $tier): array;

    /**
     * Handle payment provider webhook/callback payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): bool;
}
