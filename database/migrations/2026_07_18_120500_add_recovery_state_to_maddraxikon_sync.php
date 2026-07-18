<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
            // list=usercontribs exposes revision IDs, but no RecentChanges ID.
            $table->unsignedBigInteger('rc_id')->nullable()->change();
        });

        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            $table->timestamp('initial_watermark_at')->nullable()->after('watermark_at');
            $table->timestamp('recovery_required_at')->nullable()->after('last_seen_rc_id');
            $table->timestamp('recovery_from_at')->nullable()->after('recovery_required_at');
            $table->timestamp('recovery_until_at')->nullable()->after('recovery_from_at');
            $table->timestamp('last_recovery_succeeded_at')->nullable()->after('recovery_until_at');
            $table->timestamp('last_recovered_from_at')->nullable()->after('last_recovery_succeeded_at');
            $table->timestamp('last_recovered_until_at')->nullable()->after('last_recovered_from_at');
            $table->unsignedInteger('last_recovered_count')->default(0)->after('last_recovered_until_at');
        });

        // Existing installations must retain their established no-backfill boundary.
        DB::table('maddraxikon_sync_states')
            ->whereNotNull('watermark_at')
            ->update(['initial_watermark_at' => DB::raw('watermark_at')]);
    }

    public function down(): void
    {
        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            $table->dropColumn([
                'initial_watermark_at',
                'recovery_required_at',
                'recovery_from_at',
                'recovery_until_at',
                'last_recovery_succeeded_at',
                'last_recovered_from_at',
                'last_recovered_until_at',
                'last_recovered_count',
            ]);
        });

        // A rollback after a recovery may contain legitimate rows without rc_id.
        // Keeping the column nullable avoids destroying those audit records.
    }
};
