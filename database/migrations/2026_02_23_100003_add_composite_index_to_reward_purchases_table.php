<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_purchases', function (Blueprint $table) {
            $table->index(['user_id', 'reward_id'], 'reward_purchases_user_reward_index');
        });
    }

    public function down(): void
    {
        Schema::table('reward_purchases', function (Blueprint $table) {
            $table->dropIndex('reward_purchases_user_reward_index');
        });
    }
};
