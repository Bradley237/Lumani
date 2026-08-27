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
        Schema::create('user_challenge_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('user_challenge_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('weekly_challenge_questions')->cascadeOnDelete();
            $table->string('selected_choice')->nullable();
            $table->text('answer_text')->nullable();
            $table->integer('points_awarded')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenge_answers');
    }
};
