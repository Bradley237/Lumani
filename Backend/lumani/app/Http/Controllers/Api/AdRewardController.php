<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdRewardController extends Controller
{
    public function __construct(
        protected AdRewardService $adRewardService
    ) {}

    /**
     * Request an ad reward token to attach as custom_data when loading an AdMob rewarded ad.
     */
    public function requestToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->adRewardService->requestToken($user);

        return response()->json($result, 200);
    }

    /**
     * AdMob Server-Side Verification (SSV) callback.
     * Google AdMob calls this directly after a user finishes watching a rewarded ad.
     *
     * Note: Always returns HTTP 200 OK per Google's SSV protocol requirements,
     * even if the callback is rejected due to invalid signature, stale token, or replay.
     */
    public function rewardCallback(Request $request): JsonResponse
    {
        $this->adRewardService->handleCallback($request);

        return response()->json([
            'status' => 'ok',
        ], 200);
    }

    /**
     * Local/Testing development fallback endpoint to simulate reward completion
     * without a live AdMob account.
     */
    public function devSimulate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->adRewardService->devSimulate($user);

        return response()->json($result, 200);
    }
}
