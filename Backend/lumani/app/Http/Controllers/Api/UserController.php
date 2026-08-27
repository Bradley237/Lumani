<?php

namespace App\Http\Controllers\Api;

use App\Enums\CoinTransactionType;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user's referral code and referral stats.
     */
    public function referralCode(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $totalReferrals = $user->referrals()->count();

        $coinsEarned = (int) CoinTransaction::where('user_id', $user->id)
            ->where('type', CoinTransactionType::EarnedReferral)
            ->sum('amount');

        return response()->json([
            'referral_code' => $user->referral_code,
            'total_referrals' => $totalReferrals,
            'coins_earned_from_referrals' => $coinsEarned,
        ]);
    }
}
