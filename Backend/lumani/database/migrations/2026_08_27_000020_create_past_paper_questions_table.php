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
        Schema::create('past_paper_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('past_paper_id')->constrained('past_papers')->cascadeOnDelete();
            $table->string('type')->default('mcq');
            $table->text('question_text');
            $table->json('options')->nullable();
            $table->string('correct_choice')->nullable();
            $table->text('marking_scheme')->nullable();
            $table->unsignedInteger('max_points')->default(10);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('past_paper_questions');
    }
};
