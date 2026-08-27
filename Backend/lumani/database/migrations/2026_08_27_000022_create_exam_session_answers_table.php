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
        Schema::create('exam_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('past_paper_questions')->cascadeOnDelete();
            $table->string('selected_choice')->nullable();
            $table->text('answer_text')->nullable();
            $table->unsignedInteger('points_awarded')->nullable();
            $table->unsignedInteger('suggested_points')->nullable();
            $table->text('suggested_justification')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_session_answers');
    }
};
