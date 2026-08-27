<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_challenge_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('weekly_challenge_id')->constrained('weekly_challenges')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('status')->default('in_progress')->index(); // in_progress, submitted, graded
            $table->decimal('total_score_percent', 5, 2)->nullable();
            $table->integer('reward_coins_awarded')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekly_challenge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenge_attempts');
    }
};
