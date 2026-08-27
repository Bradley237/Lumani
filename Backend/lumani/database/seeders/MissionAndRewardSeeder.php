<?php

namespace Database\Seeders;

use App\Enums\MissionType;
use App\Models\DailyCheckinReward;
use App\Models\Mission;
use Illuminate\Database\Seeder;

class MissionAndRewardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 7-day check-in rewards
        $checkinRewards = [
            ['day' => 1, 'coin_reward' => 3],
            ['day' => 2, 'coin_reward' => 5],
            ['day' => 3, 'coin_reward' => 7],
            ['day' => 4, 'coin_reward' => 9],
            ['day' => 5, 'coin_reward' => 11],
            ['day' => 6, 'coin_reward' => 13],
            ['day' => 7, 'coin_reward' => 15],
        ];

        foreach ($checkinRewards as $reward) {
            DailyCheckinReward::updateOrCreate(
                ['day' => $reward['day']],
                ['coin_reward' => $reward['coin_reward']]
            );
        }

        // 5 missions
        $missions = [
            [
                'key' => 'daily_checkin',
                'title' => 'Daily Check-in',
                'description' => 'Check in every day to claim bonus coins and grow your streak.',
                'coin_reward' => 3,
                'type' => MissionType::DailyCheckin,
                'is_active' => true,
            ],
            [
                'key' => 'watch_ad',
                'title' => 'Watch an Ad',
                'description' => 'Watch a short video ad to earn 5 coins (up to 5 times per 20-hour window).',
                'coin_reward' => 5,
                'type' => MissionType::WatchAd,
                'is_active' => true,
            ],
            [
                'key' => 'complete_profile',
                'title' => 'Complete Profile',
                'description' => 'Fill in all your profile details to earn a one-time bonus.',
                'coin_reward' => 30,
                'type' => MissionType::OneTime,
                'is_active' => true,
            ],
            [
                'key' => 'first_quiz',
                'title' => 'Take Your First Quiz',
                'description' => 'Complete your first quiz to earn a one-time bonus.',
                'coin_reward' => 40,
                'type' => MissionType::OneTime,
                'is_active' => true,
            ],
            [
                'key' => 'refer_a_friend',
                'title' => 'Refer a Friend',
                'description' => 'Share your referral code. Earn 50 coins when a friend joins (max 1 reward per 24 hours).',
                'coin_reward' => 50,
                'type' => MissionType::Referral,
                'is_active' => true,
            ],
        ];

        foreach ($missions as $mission) {
            Mission::updateOrCreate(
                ['key' => $mission['key']],
                $mission
            );
        }
    }
}
