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
        Schema::table('chapters', function (Blueprint $table) {
            $table->integer('coin_price')->default(50)->after('order');
            $table->integer('xp_reward')->nullable()->after('coin_price');
            $table->boolean('is_free')->default(false)->after('xp_reward');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['coin_price', 'xp_reward', 'is_free']);
        });
    }
};
