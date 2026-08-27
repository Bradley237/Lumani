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
        Schema::create('past_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('exam_subsystem')->nullable();
            $table->string('level')->nullable();
            $table->integer('year');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->integer('coin_price')->default(15);
            $table->string('solution_file_path')->nullable();
            $table->integer('solution_coin_price')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('past_papers');
    }
};
