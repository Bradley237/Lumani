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
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('past_paper_id')->constrained('past_papers')->cascadeOnDelete();
            $table->unsignedInteger('max_allowed_minutes');
            $table->unsignedInteger('selected_minutes');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('in_progress');
            $table->decimal('total_score_percent', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
