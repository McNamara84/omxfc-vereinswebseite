<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_reward_policy_tiers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maddraxikon_reward_policy_id');
            $table->unsignedBigInteger('minimum_added_bytes');
            $table->unsignedInteger('points');
            $table->timestamps();

            $table->unique(
                ['maddraxikon_reward_policy_id', 'minimum_added_bytes'],
                'maddraxikon_policy_tiers_policy_minimum_unique'
            );
            $table->index(
                ['maddraxikon_reward_policy_id', 'minimum_added_bytes'],
                'maddraxikon_policy_tiers_lookup_index'
            );
            $table->foreign(
                'maddraxikon_reward_policy_id',
                'mx_policy_tiers_policy_fk'
            )->references('id')->on('maddraxikon_reward_policies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_reward_policy_tiers');
    }
};
