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
        Schema::table('weekly_challenge_questions', function (Blueprint $table) {
            $table->text('marking_scheme')->nullable()->after('correct_choice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_challenge_questions', function (Blueprint $table) {
            $table->dropColumn('marking_scheme');
        });
    }
};
