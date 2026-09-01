<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('type', 20); // integer, decimal, boolean
            $table->string('group', 50)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            [
                'key' => 'quiz_xp_per_correct_answer',
                'value' => '10',
                'type' => 'integer',
                'group' => 'quiz',
                'description' => 'XP awarded per correct question in chapter quizzes.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'quiz_xp_completion_bonus',
                'value' => '20',
                'type' => 'integer',
                'group' => 'quiz',
                'description' => 'Bonus XP awarded for submitting a completed quiz.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'xp_to_coins_ratio_xp',
                'value' => '1500',
                'type' => 'integer',
                'group' => 'xp_economy',
                'description' => 'XP threshold chunk required to convert into coins.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'xp_to_coins_ratio_coins',
                'value' => '50',
                'type' => 'integer',
                'group' => 'xp_economy',
                'description' => 'Coins awarded per XP conversion threshold chunk.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'watch_ad_daily_cap',
                'value' => '5',
                'type' => 'integer',
                'group' => 'missions',
                'description' => 'Maximum number of rewarded ads a student can watch per reset window.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'watch_ad_reset_hours',
                'value' => '20',
                'type' => 'integer',
                'group' => 'missions',
                'description' => 'Rolling window in hours before watched ad count resets.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'checkin_reset_hours',
                'value' => '20',
                'type' => 'integer',
                'group' => 'missions',
                'description' => 'Cooldown window in hours before a student can claim the next daily check-in.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'referral_cap_hours',
                'value' => '24',
                'type' => 'integer',
                'group' => 'missions',
                'description' => 'Rolling window in hours between eligible referral rewards for a referrer.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam_time_cap_mcq_minutes',
                'value' => '90',
                'type' => 'integer',
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for MCQ-only past paper exam sessions.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam_time_cap_structural_minutes',
                'value' => '180',
                'type' => 'integer',
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for structural/essay-only exam sessions.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam_time_cap_mixed_minutes',
                'value' => '240',
                'type' => 'integer',
                'group' => 'exam_sessions',
                'description' => 'Maximum allowed time in minutes for mixed composition past paper exam sessions.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge_reward_high_threshold_percent',
                'value' => '95',
                'type' => 'decimal',
                'group' => 'weekly_challenges',
                'description' => 'Score percentage threshold required to earn the highest tier challenge coin reward.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge_reward_high_coins',
                'value' => '100',
                'type' => 'integer',
                'group' => 'weekly_challenges',
                'description' => 'Coins awarded for achieving the highest weekly challenge tier.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge_reward_mid_threshold_percent',
                'value' => '70',
                'type' => 'decimal',
                'group' => 'weekly_challenges',
                'description' => 'Score percentage threshold required to earn the mid-tier challenge coin reward.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge_reward_mid_coins',
                'value' => '50',
                'type' => 'integer',
                'group' => 'weekly_challenges',
                'description' => 'Coins awarded for achieving the mid-tier weekly challenge score.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('business_settings')->insert($defaults);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
