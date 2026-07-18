<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_reward_events', function (Blueprint $table): void {
            $table->id();
            $table->string('wiki_key', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_link_id')
                ->nullable()
                ->constrained('maddraxikon_account_links')
                ->nullOnDelete();
            $table->foreignId('source_contribution_id')
                ->nullable()
                ->constrained('maddraxikon_contributions')
                ->nullOnDelete();
            $table->string('action_key', 100);
            $table->string('source_key', 191);
            $table->unsignedBigInteger('source_revision_id');
            $table->unsignedBigInteger('session_anchor_revision_id')->nullable();
            $table->date('activity_date');
            $table->unsignedInteger('sequence_number')->nullable();
            $table->foreignId('baxx_earning_rule_id')
                ->nullable()
                ->constrained('baxx_earning_rules')
                ->nullOnDelete();
            $table->unsignedInteger('rule_points')->default(0);
            $table->unsignedInteger('rule_every_count')->default(1);
            $table->timestamp('rule_updated_at')->nullable();
            $table->unsignedInteger('candidate_points')->default(0);
            $table->unsignedInteger('awarded_points')->default(0);
            $table->unsignedInteger('capped_points')->default(0);
            $table->string('status', 32);
            $table->string('status_reason', 191)->nullable();
            $table->foreignId('user_point_id')
                ->nullable()
                ->unique()
                ->constrained('user_points')
                ->nullOnDelete();
            $table->foreignId('reversal_user_point_id')
                ->nullable()
                ->unique()
                ->constrained('user_points')
                ->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['wiki_key', 'source_key'],
                'maddraxikon_rewards_wiki_source_unique'
            );
            $table->index(
                ['user_id', 'activity_date'],
                'maddraxikon_rewards_user_date_index'
            );
            $table->index(
                ['status', 'activity_date'],
                'maddraxikon_rewards_status_date_index'
            );
            $table->index(
                ['user_id', 'action_key', 'sequence_number'],
                'maddraxikon_rewards_user_action_sequence_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_reward_events');
    }
};
