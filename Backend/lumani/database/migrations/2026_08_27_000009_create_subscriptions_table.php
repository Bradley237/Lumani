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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tier')->index(); // tier_2000, tier_5000
            $table->integer('coin_allotment'); // 500 or 1500
            $table->integer('amount_fcfa'); // 2000 or 5000
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('status')->default('active')->index(); // active, expired, cancelled
            $table->timestamps();

            $table->index(['user_id', 'status', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
