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
        Schema::create('user_chapter_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['user_id', 'chapter_id']);
        });

        Schema::create('user_past_paper_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('past_paper_id')->constrained('past_papers')->cascadeOnDelete();
            $table->timestamp('paper_unlocked_at')->nullable();
            $table->timestamp('solution_unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'past_paper_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_past_paper_unlocks');
        Schema::dropIfExists('user_chapter_unlocks');
    }
};
