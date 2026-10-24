<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maddraxikon_account_links', 'streak_verified_at_epoch')) {
            Schema::table('maddraxikon_account_links', function (Blueprint $table): void {
                $table->unsignedBigInteger('streak_verified_at_epoch')->nullable();
            });
            DB::table('maddraxikon_account_links')->update([
                'streak_verified_at_epoch' => DB::raw('verified_at_epoch'),
            ]);
        }
        if (! Schema::hasColumn('maddraxikon_contributions', 'streak_activity_date')) {
            Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
                $table->date('streak_activity_date')->nullable();
                $table->unsignedBigInteger('streak_verified_at_epoch')->nullable();
                $table->string('streak_status', 16)->default('pending');
                $table->string('streak_reason', 191)->nullable();
                $table->unsignedBigInteger('streak_checked_at_epoch')->nullable();
                $table->unsignedBigInteger('streak_next_check_epoch')->default(0);
                $table->unsignedInteger('streak_check_version')->default(0);
                $table->boolean('streak_bot_verified')->default(false);
                $table->foreignId('streak_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('streak_review_reason')->nullable();
                $table->index(['wiki_key', 'streak_next_check_epoch'], 'mx_streak_due');
                $table->index(['wiki_key', 'user_id', 'streak_activity_date'], 'mx_streak_sources_day');
            });
        }
        if (! Schema::hasTable('maddraxikon_streak_runs')) {
            Schema::create('maddraxikon_streak_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('wiki_key', 64)->unique();
                $table->unsignedBigInteger('starts_at_epoch');
                $table->unsignedBigInteger('last_evaluated_epoch')->nullable();
                $table->unsignedBigInteger('last_rechecked_epoch')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('maddraxikon_streak_states')) {
            Schema::create('maddraxikon_streak_states', function (Blueprint $table): void {
                $table->id();
                $table->string('wiki_key', 64);
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('dirty_from')->nullable();
                $table->date('pending_from')->nullable();
                $table->timestamps();
                $table->unique(['wiki_key', 'user_id'], 'mx_streak_state_unique');
            });
        }
        if (! Schema::hasTable('maddraxikon_streak_days')) {
            Schema::create('maddraxikon_streak_days', function (Blueprint $table): void {
                $table->id();
                $table->string('wiki_key', 64);
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('activity_date');
                $table->string('status', 16)->default('pending');
                $table->unsignedInteger('valid_count')->default(0);
                $table->unsignedInteger('pending_count')->default(0);
                $table->unsignedInteger('streak_length')->nullable();
                $table->unsignedInteger('target_points')->nullable();
                $table->bigInteger('settled_points')->default(0);
                $table->unsignedInteger('calculation_version')->default(0);
                $table->unsignedBigInteger('starts_at_epoch');
                $table->timestamps();
                $table->unique(['wiki_key', 'user_id', 'activity_date'], 'mx_streak_day_unique');
            });
        }
        if (! Schema::hasTable('maddraxikon_streak_bookings')) {
            Schema::create('maddraxikon_streak_bookings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('streak_day_id')->constrained('maddraxikon_streak_days')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_point_id')->nullable()->unique()->constrained()->nullOnDelete();
                $table->bigInteger('points_delta');
                $table->unsignedInteger('previous_points');
                $table->unsignedInteger('target_points');
                $table->unsignedInteger('calculation_version');
                $table->string('reason', 191);
                $table->json('source_revision_ids');
                $table->timestamps();
                $table->unique(['streak_day_id', 'calculation_version'], 'mx_streak_booking_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep the journal, calculation state and source snapshots across code rollbacks.
        // Removing audit data requires a separate, explicitly reviewed data migration.
    }
};
