<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_reward_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('maddraxikon_reward_policy_id')
                ->nullable()
                ->after('baxx_earning_rule_id');
            $table->unsignedBigInteger('maddraxikon_reward_policy_tier_id')
                ->nullable()
                ->after('maddraxikon_reward_policy_id');
            $table->timestamp('policy_effective_from')->nullable()->after('rule_updated_at');
            $table->unsignedBigInteger('policy_effective_from_epoch')->nullable()->after('policy_effective_from');
            $table->unsignedBigInteger('measured_added_bytes')->nullable()->after('policy_effective_from_epoch');
            $table->unsignedBigInteger('matched_minimum_added_bytes')->nullable()->after('measured_added_bytes');
            $table->unsignedBigInteger('policy_new_article_minimum_bytes')->nullable()->after('matched_minimum_added_bytes');
            $table->string('calculation_mode', 24)->default('legacy')->after('policy_new_article_minimum_bytes');

            $table->foreign(
                'maddraxikon_reward_policy_id',
                'mx_reward_events_policy_fk'
            )->references('id')->on('maddraxikon_reward_policies')->nullOnDelete();
            $table->foreign(
                'maddraxikon_reward_policy_tier_id',
                'mx_reward_events_policy_tier_fk'
            )->references('id')->on('maddraxikon_reward_policy_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maddraxikon_reward_events', function (Blueprint $table): void {
            $table->dropForeign('mx_reward_events_policy_tier_fk');
            $table->dropForeign('mx_reward_events_policy_fk');
            $table->dropColumn([
                'maddraxikon_reward_policy_tier_id',
                'maddraxikon_reward_policy_id',
                'policy_effective_from',
                'policy_effective_from_epoch',
                'measured_added_bytes',
                'matched_minimum_added_bytes',
                'policy_new_article_minimum_bytes',
                'calculation_mode',
            ]);
        });
    }
};
