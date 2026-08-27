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
        Schema::table('user_challenge_answers', function (Blueprint $table) {
            $table->integer('suggested_points')->nullable()->after('points_awarded');
            $table->text('suggested_justification')->nullable()->after('suggested_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_challenge_answers', function (Blueprint $table) {
            $table->dropColumn(['suggested_points', 'suggested_justification']);
        });
    }
};
