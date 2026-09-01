<?php

namespace Database\Seeders;

use App\Enums\BusinessSettingType;
use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

class BusinessSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'quiz_xp_per_correct_answer',
                'value' => '10',
                'type' => BusinessSettingType::Integer,
                'group' => 'quiz',
                'description' => 'XP awarded per correct question in chapter quizzes.',
            ],
            [
                'key' => 'quiz_xp_completion_bonus',
                'value' => '20',
                'type' => BusinessSettingType::Integer,
                'group' => 'quiz',
                'description' => 'Bonus XP awarded for submitting a completed quiz.',
            ],
            [
                'key' => 'xp_to_coins_ratio_xp',
                'value' => '1500',
                'type' => BusinessSettingType::Integer,
                'group' => 'xp_economy',
                'description' => 'XP threshold chunk required to convert into coins.',
            ],
            [
                'key' => 'xp_to_coins_ratio_coins',
                'value' => '50',
                'type' => BusinessSettingType::Integer,
                'group' => 'xp_economy',
                'description' => 'Coins awarded per XP conversion threshold chunk.',
            ],
            [
                'key' => 'watch_ad_daily_cap',
                'value' => '5',
                'type' => BusinessSettingType::Integer,
                'group' => 'missions',
                'description' => 'Maximum number of rewarded ads a student can watch per reset window.',
            ],
            [
                'key' => 'watch_ad_reset_hours',
                'value' => '20',
                'type' => BusinessSettingType::Integer,
                'group' => 'missions',
                'description' => 'Rolling window in hours before watched ad count resets.',
            ],
            [
                'key' => 'checkin_reset_hours',
                'value' => '20',
                'type' => BusinessSettingType::Integer,
                'group' => 'missions',
                'description' => 'Cooldown window in hours before a student can claim the next daily check-in.',
            ],
            [
                'key' => 'referral_cap_hours',
                'value' => '24',
                'type' => BusinessSettingType::Integer,
                'group' => 'missions',
                'description' => 'Rolling window in hours between eligible referral rewards for a referrer.',
            ],
            [
                'key' => 'exam_time_cap_mcq_minutes',
                'value' => '90',
                'type' => BusinessSettingType::Integer,
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for MCQ-only past paper exam sessions.',
            ],
            [
                'key' => 'exam_time_cap_structural_minutes',
                'value' => '180',
                'type' => BusinessSettingType::Integer,
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for structural/essay-only exam sessions.',
            ],
            [
                'key' => 'exam_time_cap_mixed_minutes',
                'value' => '240',
                'type' => BusinessSettingType::Integer,
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for mixed composition past paper exam sessions.',
            ],
            [
                'key' => 'challenge_reward_high_threshold_percent',
                'value' => '95',
                'type' => BusinessSettingType::Decimal,
                'group' => 'weekly_challenges',
                'description' => 'Score percentage threshold required to earn the highest tier challenge coin reward.',
            ],
            [
                'key' => 'challenge_reward_high_coins',
                'value' => '100',
                'type' => BusinessSettingType::Integer,
                'group' => 'weekly_challenges',
                'description' => 'Coins awarded for achieving the highest weekly challenge tier.',
            ],
            [
                'key' => 'challenge_reward_mid_threshold_percent',
                'value' => '70',
                'type' => BusinessSettingType::Decimal,
                'group' => 'weekly_challenges',
                'description' => 'Score percentage threshold required to earn the mid-tier challenge coin reward.',
            ],
            [
                'key' => 'challenge_reward_mid_coins',
                'value' => '50',
                'type' => BusinessSettingType::Integer,
                'group' => 'weekly_challenges',
                'description' => 'Coins awarded for achieving the mid-tier weekly challenge score.',
            ],
        ];

        foreach ($settings as $setting) {
            BusinessSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        BusinessSetting::flushRuntimeCache();
    }
}
