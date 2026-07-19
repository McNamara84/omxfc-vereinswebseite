<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_reward_events', function (Blueprint $table): void {
            $table->boolean('activity_pending')
                ->default(false)
                ->after('awarded_at');
            $table->index(
                ['activity_pending', 'user_id', 'id'],
                'maddraxikon_rewards_pending_activity_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maddraxikon_reward_events', function (Blueprint $table): void {
            $table->dropIndex('maddraxikon_rewards_pending_activity_index');
            $table->dropColumn('activity_pending');
        });
    }
};
