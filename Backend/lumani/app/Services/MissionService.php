<?php

namespace App\Services;

use App\Enums\AdRewardStatus;
use App\Enums\CoinTransactionType;
use App\Enums\MissionType;
use App\Models\AdRewardRequest;
use App\Models\BusinessSetting;
use App\Models\CoinTransaction;
use App\Models\DailyCheckinReward;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMissionProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MissionService
{
    public function __construct(
        protected CoinService $coinService
    ) {}

    /**
     * Claim daily check-in reward.
     *
     * @return array{
     *     message: string,
     *     streak_day: int,
     *     coins_earned: int,
     *     next_checkin_at: string,
     *     coin_balance: int
     * }
     */
    public function checkin(User $user): array
    {
        $mission = Mission::firstOrCreate(
            ['key' => 'daily_checkin'],
            [
                'title' => 'Daily Check-in',
                'description' => 'Check in every day to claim bonus coins and grow your streak.',
                'coin_reward' => 3,
                'type' => MissionType::DailyCheckin,
                'is_active' => true,
            ]
        );

        $progress = UserMissionProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'mission_id' => $mission->id,
            ],
            [
                'current_streak_day' => 0,
                'completed' => false,
            ]
        );

        $now = now();
        $streakDay = 1;

        $checkinResetHours = (int) BusinessSetting::get('checkin_reset_hours', 20);

        if ($progress->last_completed_at !== null) {
            $lastCompleted = Carbon::parse($progress->last_completed_at);
            $secondsSince = $lastCompleted->diffInSeconds($now);

            $resetSeconds = $checkinResetHours * 3600;
            $skipSeconds = ($checkinResetHours * 2) * 3600;

            if ($secondsSince < $resetSeconds) {
                $nextAvailable = $lastCompleted->copy()->addHours($checkinResetHours);

                throw ValidationException::withMessages([
                    'checkin' => "You have already checked in within the last {$checkinResetHours} hours. Next check-in available at {$nextAvailable->toIso8601String()}.",
                ]);
            }

            if ($secondsSince > $skipSeconds) {
                // Skipped a day -> reset to Day 1
                $streakDay = 1;
            } else {
                // Within reset window -> streak increments
                $currentStreak = $progress->current_streak_day ?? 0;
                $streakDay = ($currentStreak % 7) + 1;
            }
        } else {
            $streakDay = 1;
        }

        $rewardModel = DailyCheckinReward::where('day', $streakDay)->first();
        $coinReward = $rewardModel ? $rewardModel->coin_reward : (3 + ($streakDay - 1) * 2);

        $this->coinService->award($user, $coinReward, CoinTransactionType::EarnedMission, $mission);

        $progress->current_streak_day = $streakDay;
        $progress->last_completed_at = $now;
        $progress->completed = true;
        $progress->save();

        $user->day_streak = $streakDay;
        $user->save();

        return [
            'message' => "Day {$streakDay} check-in completed successfully.",
            'streak_day' => $streakDay,
            'coins_earned' => $coinReward,
            'next_checkin_at' => $now->copy()->addHours($checkinResetHours)->toIso8601String(),
            'coin_balance' => $this->coinService->getBalance($user),
        ];
    }

    /**
     * Claim reward for watching an ad (capped at 5 per rolling 20 hours).
     *
     * @return array{
     *     message: string,
     *     coins_earned: int,
     *     ads_watched_in_window: int,
     *     remaining_ads: int,
     *     coin_balance: int
     * }
     */
    public function watchAd(User $user): array
    {
        $mission = Mission::firstOrCreate(
            ['key' => 'watch_ad'],
            [
                'title' => 'Watch an Ad',
                'description' => 'Watch a short video ad to earn 5 coins (up to 5 times per 20-hour window).',
                'coin_reward' => 5,
                'type' => MissionType::WatchAd,
                'is_active' => true,
            ]
        );

        $windowStart = now()->subHours(20);

        $watchedInWindow = CoinTransaction::where('user_id', $user->id)
            ->where('type', CoinTransactionType::EarnedMission)
            ->where('reference_type', $mission->getMorphClass())
            ->where('reference_id', $mission->id)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($watchedInWindow >= 5) {
            throw ValidationException::withMessages([
                'ad' => 'You have reached the maximum limit of 5 rewarded ads in a 20-hour rolling window.',
            ]);
        }

        $coinReward = $mission->coin_reward > 0 ? $mission->coin_reward : 5;
        $this->coinService->award($user, $coinReward, CoinTransactionType::EarnedMission, $mission);

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

        $newCount = $watchedInWindow + 1;

        return [
            'message' => 'Reward claimed for watching ad.',
            'coins_earned' => $coinReward,
            'ads_watched_in_window' => $newCount,
            'remaining_ads' => max(0, 5 - $newCount),
            'coin_balance' => $this->coinService->getBalance($user),
        ];
    }

    /**
     * Complete a one-time mission.
     *
     * @return array{
     *     message: string,
     *     mission: string,
     *     coins_earned: int,
     *     coin_balance: int
     * }
     */
    public function completeOneTime(User $user, string $missionKey): array
    {
        $mission = Mission::where('key', $missionKey)
            ->where('is_active', true)
            ->first();

        if (! $mission) {
            throw ValidationException::withMessages([
                'mission' => "Mission '{$missionKey}' does not exist or is inactive.",
            ]);
        }

        if ($mission->type !== MissionType::OneTime) {
            throw ValidationException::withMessages([
                'mission' => "Mission '{$missionKey}' is not a one-time mission.",
            ]);
        }

        $progress = UserMissionProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'mission_id' => $mission->id,
            ],
            [
                'completed' => false,
            ]
        );

        if ($progress->completed) {
            throw ValidationException::withMessages([
                'mission' => "Mission '{$mission->title}' has already been completed.",
            ]);
        }

        $this->coinService->award($user, $mission->coin_reward, CoinTransactionType::EarnedMission, $mission);

        $progress->completed = true;
        $progress->last_completed_at = now();
        $progress->save();

        return [
            'message' => "Mission '{$mission->title}' completed successfully.",
            'mission' => $mission->key,
            'coins_earned' => $mission->coin_reward,
            'coin_balance' => $this->coinService->getBalance($user),
        ];
    }

    /**
     * Process referral reward upon user registration.
     */
    public function processReferral(User $newUser, string $referralCode): ?User
    {
        $normalizedCode = strtoupper(trim($referralCode));

        $referrer = User::where('referral_code', $normalizedCode)->first();

        if (! $referrer || $referrer->id === $newUser->id) {
            return null;
        }

        $newUser->referred_by_user_id = $referrer->id;
        $newUser->save();

        $referralCapHours = (int) BusinessSetting::get('referral_cap_hours', 24);
        $recentReferralRewards = CoinTransaction::where('user_id', $referrer->id)
            ->where('type', CoinTransactionType::EarnedReferral)
            ->where('created_at', '>=', now()->subHours($referralCapHours))
            ->count();

        if ($recentReferralRewards < 1) {
            $mission = Mission::where('key', 'refer_a_friend')->first();
            $rewardAmount = $mission ? $mission->coin_reward : 50;

            $this->coinService->award($referrer, $rewardAmount, CoinTransactionType::EarnedReferral, $newUser);
        }

        return $referrer;
    }

    /**
     * Convert available XP to coins.
     *
     * @return array{
     *     message: string,
     *     xp_converted: int,
     *     coins_earned: int,
     *     xp_converted_total: int,
     *     experience_points: int,
     *     remaining_unconverted_xp: int,
     *     coin_balance: int
     * }
     */
    public function convertXp(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $xpThreshold = (int) BusinessSetting::get('xp_to_coins_ratio_xp', 1500);
            $coinsPerChunk = (int) BusinessSetting::get('xp_to_coins_ratio_coins', 50);

            $availableXp = max(0, $lockedUser->experience_points - $lockedUser->xp_converted_total);
            $chunks = $xpThreshold > 0 ? intdiv($availableXp, $xpThreshold) : 0;

            if ($chunks < 1) {
                throw ValidationException::withMessages([
                    'xp' => "You need at least {$xpThreshold} unconverted XP to convert to coins.",
                ]);
            }

            $xpToConvert = $chunks * $xpThreshold;
            $coinsEarned = $chunks * $coinsPerChunk;

            $lockedUser->xp_converted_total += $xpToConvert;
            $lockedUser->save();

            $this->coinService->award($lockedUser, $coinsEarned, CoinTransactionType::EarnedXpConversion, $lockedUser);

            $user->xp_converted_total = $lockedUser->xp_converted_total;
            $user->coin_balance = $lockedUser->coin_balance;

            return [
                'message' => 'XP converted to coins successfully.',
                'xp_converted' => $xpToConvert,
                'coins_earned' => $coinsEarned,
                'xp_converted_total' => $lockedUser->xp_converted_total,
                'experience_points' => $lockedUser->experience_points,
                'remaining_unconverted_xp' => $lockedUser->experience_points - $lockedUser->xp_converted_total,
                'coin_balance' => $lockedUser->coin_balance,
            ];
        });
    }

    /**
     * Get all active missions with the user's progress.
     *
     * @return array{
     *     missions: list<array<string, mixed>>,
     *     daily_checkin_rewards: list<DailyCheckinReward>,
     *     user_streak: int
     * }
     */
    public function getMissionsWithProgress(User $user): array
    {
        $missions = Mission::where('is_active', true)->get();
        $userProgress = UserMissionProgress::where('user_id', $user->id)
            ->get()
            ->keyBy('mission_id');

        $checkinRewards = DailyCheckinReward::orderBy('day')->get();

        $adMission = $missions->firstWhere('key', 'watch_ad');
        $adsWatchedIn20h = 0;
        $watchAdResetHours = (int) BusinessSetting::get('watch_ad_reset_hours', 20);
        $watchAdDailyCap = (int) BusinessSetting::get('watch_ad_daily_cap', 5);

        if ($adMission) {
            $windowStart = now()->subHours($watchAdResetHours);
            $redeemedRequests = AdRewardRequest::where('user_id', $user->id)
                ->where('status', AdRewardStatus::Redeemed)
                ->where(function ($query) use ($windowStart) {
                    $query->where('redeemed_at', '>=', $windowStart)
                        ->orWhere(function ($sub) use ($windowStart) {
                            $sub->whereNull('redeemed_at')
                                ->where('created_at', '>=', $windowStart);
                        });
                })
                ->count();

            $legacyCount = CoinTransaction::where('user_id', $user->id)
                ->where('type', CoinTransactionType::EarnedMission)
                ->where('reference_type', $adMission->getMorphClass())
                ->where('reference_id', $adMission->id)
                ->where('created_at', '>=', $windowStart)
                ->count();

            $adsWatchedIn20h = $redeemedRequests + $legacyCount;
        }

        $now = now();
        $checkinResetHours = (int) BusinessSetting::get('checkin_reset_hours', 20);

        $missionData = $missions->map(function (Mission $mission) use ($userProgress, $adsWatchedIn20h, $now, $user, $checkinResetHours, $watchAdDailyCap): array {
            /** @var UserMissionProgress|null $prog */
            $prog = $userProgress->get($mission->id);

            $data = [
                'id' => $mission->id,
                'key' => $mission->key,
                'title' => $mission->title,
                'description' => $mission->description,
                'coin_reward' => $mission->coin_reward,
                'type' => $mission->type->value,
                'is_active' => $mission->is_active,
                'completed' => $prog ? $prog->completed : false,
                'last_completed_at' => $prog?->last_completed_at?->toIso8601String(),
            ];

            if ($mission->type === MissionType::DailyCheckin) {
                $canCheckin = true;
                $nextCheckinAt = null;

                if ($prog && $prog->last_completed_at) {
                    $last = Carbon::parse($prog->last_completed_at);
                    if ($last->diffInSeconds($now) < $checkinResetHours * 3600) {
                        $canCheckin = false;
                        $nextCheckinAt = $last->copy()->addHours($checkinResetHours)->toIso8601String();
                    }
                }

                $data['current_streak_day'] = $prog ? ($prog->current_streak_day ?? 0) : 0;
                $data['can_checkin'] = $canCheckin;
                $data['next_checkin_at'] = $nextCheckinAt;
            } elseif ($mission->type === MissionType::WatchAd) {
                $data['ads_watched_in_window'] = $adsWatchedIn20h;
                $data['remaining_ads'] = max(0, $watchAdDailyCap - $adsWatchedIn20h);
                $data['can_watch_ad'] = $adsWatchedIn20h < $watchAdDailyCap;
            } elseif ($mission->type === MissionType::Referral) {
                $data['referral_code'] = $user->referral_code;
                $data['total_referrals'] = $user->referrals()->count();
            }

            return $data;
        });

        return [
            'missions' => array_values($missionData->all()),
            'daily_checkin_rewards' => array_values($checkinRewards->all()),
            'user_streak' => (int) $user->day_streak,
        ];
    }
}
