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
        Schema::create('weekly_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('exam_subsystem')->nullable();
            $table->string('level')->nullable();
            $table->string('title');
            $table->integer('time_limit_minutes')->default(30);
            $table->dateTime('week_start_date');
            $table->dateTime('week_end_date');
            $table->string('status')->default('draft')->index(); // draft, published, closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'week_start_date', 'week_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_challenges');
    }
};
