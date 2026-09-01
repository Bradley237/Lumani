<?php

namespace App\Services;

use App\Enums\AdRewardStatus;
use App\Enums\CoinTransactionType;
use App\Models\AdRewardRequest;
use App\Models\BusinessSetting;
use App\Models\CoinTransaction;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMissionProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdRewardService
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Count ads watched/redeemed by the user in the rolling window.
     */
    public function getAdsWatchedInWindow(User $user, ?int $hours = null): int
    {
        $hours = $hours ?? (int) BusinessSetting::get('watch_ad_reset_hours', 20);
        $windowStart = now()->subHours($hours);

        // Count redeemed requests
        $redeemedCount = AdRewardRequest::where('user_id', $user->id)
            ->where('status', AdRewardStatus::Redeemed)
            ->where(function ($query) use ($windowStart) {
                $query->where('redeemed_at', '>=', $windowStart)
                    ->orWhere(function ($sub) use ($windowStart) {
                        $sub->whereNull('redeemed_at')
                            ->where('created_at', '>=', $windowStart);
                    });
            })
            ->count();

        // Also check legacy watch_ad mission transactions for backward compatibility
        $legacyCount = 0;
        $mission = Mission::where('key', 'watch_ad')->first();
        if ($mission) {
            $legacyCount = CoinTransaction::where('user_id', $user->id)
                ->where('type', CoinTransactionType::EarnedMission)
                ->where('reference_type', $mission->getMorphClass())
                ->where('reference_id', $mission->id)
                ->where('created_at', '>=', $windowStart)
                ->count();
        }

        return $redeemedCount + $legacyCount;
    }

    /**
     * Request an ad reward token after checking the 5-per-20h cap.
     *
     * @return array{
     *     token: string,
     *     status: string,
     *     remaining_ads: int,
     *     expires_at: string
     * }
     */
    public function requestToken(User $user): array
    {
        $resetHours = (int) BusinessSetting::get('watch_ad_reset_hours', 20);
        $dailyCap = (int) BusinessSetting::get('watch_ad_daily_cap', 5);

        $watchedInWindow = $this->getAdsWatchedInWindow($user, $resetHours);

        if ($watchedInWindow >= $dailyCap) {
            throw ValidationException::withMessages([
                'ad' => "You have reached the maximum limit of {$dailyCap} rewarded ads in a {$resetHours}-hour rolling window.",
            ]);
        }

        $token = Str::random(40);

        $request = AdRewardRequest::create([
            'user_id' => $user->id,
            'token' => $token,
            'status' => AdRewardStatus::Pending,
        ]);

        return [
            'token' => $request->token,
            'status' => $request->status->value,
            'remaining_ads' => max(0, $dailyCap - ($watchedInWindow + 1)),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ];
    }

    /**
     * Fetch and cache AdMob SSV verifier public keys for 24 hours.
     *
     * @return array<string, mixed>
     */
    public function fetchPublicKeys(): array
    {
        return Cache::remember('admob_ssv_verifier_keys', 86400, function (): array {
            $url = (string) config('admob.verifier_keys_url', 'https://gstatic.com/admob/reward/verifier-keys.json');

            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    $data = $response->json();

                    return is_array($data) ? $data : [];
                }
            } catch (\Throwable) {
                // If unreachable, fallback to empty array
            }

            return [];
        });
    }

    /**
     * Verify the ECDSA signature of an AdMob SSV callback request.
     */
    public function verifySignature(Request $request): bool
    {
        $keyId = $request->query('key_id');
        $signature = $request->query('signature');

        if (blank($keyId) || blank($signature)) {
            return false;
        }

        $keysData = $this->fetchPublicKeys();
        $publicKeyPem = null;

        foreach ($keysData['keys'] ?? [] as $key) {
            if ((string) ($key['keyId'] ?? '') === (string) $keyId) {
                $publicKeyPem = $key['pem'] ?? null;
                break;
            }
        }

        if (! $publicKeyPem) {
            return false;
        }

        // AdMob query string format:
        // The message is the portion of the query string before &signature=
        $queryString = (string) ($request->server->get('QUERY_STRING') ?: $request->getQueryString());

        $sigPos = strpos($queryString, '&signature=');
        if ($sigPos !== false) {
            $message = substr($queryString, 0, $sigPos);
        } elseif (str_starts_with($queryString, 'signature=')) {
            $message = '';
        } else {
            // Fallback: strip signature and key_id
            $params = $request->query();
            unset($params['signature'], $params['key_id']);
            $message = http_build_query($params);
        }

        // URL-safe Base64 decode
        $rawSignature = base64_decode(strtr((string) $signature, '-_', '+/'));
        if ($rawSignature === false || $rawSignature === '') {
            return false;
        }

        $verify = openssl_verify($message, $rawSignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $verify === 1;
    }

    /**
     * Handle the incoming AdMob SSV callback.
     * Rejects invalid signature, unknown token, expired token (>10m), or already redeemed.
     */
    public function handleCallback(Request $request): bool
    {
        if (! $this->verifySignature($request)) {
            return false;
        }

        $token = $request->query('custom_data');
        if (blank($token)) {
            return false;
        }

        /** @var AdRewardRequest|null $rewardRequest */
        $rewardRequest = AdRewardRequest::where('token', $token)->first();

        if (! $rewardRequest) {
            return false;
        }

        if (! $rewardRequest->isPending()) {
            return false;
        }

        if ($rewardRequest->isOlderThanMinutes(10)) {
            $rewardRequest->status = AdRewardStatus::Expired;
            $rewardRequest->save();

            return false;
        }

        DB::transaction(function () use ($rewardRequest): void {
            $rewardRequest->status = AdRewardStatus::Redeemed;
            $rewardRequest->redeemed_at = now();
            $rewardRequest->save();

            $this->coinService->award(
                $rewardRequest->user,
                5,
                CoinTransactionType::EarnedMission,
                $rewardRequest
            );

            $mission = Mission::where('key', 'watch_ad')->first();
            if ($mission) {
                UserMissionProgress::updateOrCreate(
                    [
                        'user_id' => $rewardRequest->user_id,
                        'mission_id' => $mission->id,
                    ],
                    [
                        'last_completed_at' => now(),
                        'completed' => true,
                    ]
                );
            }
        });

        return true;
    }

    /**
     * Local/Testing fallback to simulate ad reward without a real AdMob account.
     *
     * @return array{
     *     message: string,
     *     token: string,
     *     coins_earned: int,
     *     coin_balance: int
     * }
     */
    public function devSimulate(User $user): array
    {
        /** @var AdRewardRequest|null $rewardRequest */
        $rewardRequest = AdRewardRequest::where('user_id', $user->id)
            ->where('status', AdRewardStatus::Pending)
            ->latest('id')
            ->first();

        if (! $rewardRequest) {
            throw ValidationException::withMessages([
                'ad' => 'No pending ad reward request found for user.',
            ]);
        }

        if ($rewardRequest->isOlderThanMinutes(10)) {
            $rewardRequest->status = AdRewardStatus::Expired;
            $rewardRequest->save();

            throw ValidationException::withMessages([
                'ad' => 'Pending ad reward request has expired (> 10 minutes).',
            ]);
        }

        DB::transaction(function () use ($rewardRequest, $user): void {
            $rewardRequest->status = AdRewardStatus::Redeemed;
            $rewardRequest->redeemed_at = now();
            $rewardRequest->save();

            $this->coinService->award(
                $user,
                5,
                CoinTransactionType::EarnedMission,
                $rewardRequest
            );

            $mission = Mission::where('key', 'watch_ad')->first();
            if ($mission) {
                UserMissionProgress::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'mission_id' => $mission->id,
                    ],
                    [
                        'last_completed_at' => now(),
                        'completed' => true,
                    ]
                );
            }
        });

        return [
            'message' => 'Reward redeemed successfully.',
            'token' => $rewardRequest->token,
            'coins_earned' => 5,
            'coin_balance' => $this->coinService->getBalance($user),
        ];
    }
}
