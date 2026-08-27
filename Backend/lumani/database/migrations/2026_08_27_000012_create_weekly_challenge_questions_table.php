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
        Schema::create('weekly_challenge_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_challenge_id')->constrained('weekly_challenges')->cascadeOnDelete();
            $table->string('type')->default('mcq'); // mcq, structural
            $table->text('question_text');
            $table->json('options')->nullable(); // For MCQ: {"A": "...", "B": "..."}
            $table->string('correct_choice')->nullable(); // For MCQ: "A", "B", etc.
            $table->integer('max_points')->default(1);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['weekly_challenge_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_challenge_questions');
    }
};
