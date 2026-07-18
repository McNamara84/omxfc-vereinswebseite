<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_account_links', function (Blueprint $table): void {
            foreach ([
                'first_verified_at',
                'verified_at',
                'disconnected_at',
                'consented_at',
            ] as $column) {
                $table->unsignedBigInteger($column.'_epoch')
                    ->nullable()
                    ->after($column);
            }

            $table->index(
                ['status', 'verified_at_epoch'],
                'maddraxikon_links_status_verified_epoch_index',
            );
        });

        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            foreach ([
                'watermark_at',
                'initial_watermark_at',
                'last_started_at',
                'last_succeeded_at',
                'last_error_at',
                'recovery_required_at',
                'recovery_from_at',
                'recovery_until_at',
                'last_recovery_succeeded_at',
                'last_recovered_from_at',
                'last_recovered_until_at',
            ] as $column) {
                $table->unsignedBigInteger($column.'_epoch')
                    ->nullable()
                    ->after($column);
            }

            $table->index(
                'watermark_at_epoch',
                'maddraxikon_sync_watermark_epoch_index',
            );
            $table->index(
                'recovery_required_at_epoch',
                'maddraxikon_sync_recovery_epoch_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('maddraxikon_account_links', function (Blueprint $table): void {
            $table->dropIndex('maddraxikon_links_status_verified_epoch_index');
            $table->dropColumn([
                'first_verified_at_epoch',
                'verified_at_epoch',
                'disconnected_at_epoch',
                'consented_at_epoch',
            ]);
        });

        Schema::table('maddraxikon_sync_states', function (Blueprint $table): void {
            $table->dropIndex('maddraxikon_sync_watermark_epoch_index');
            $table->dropIndex('maddraxikon_sync_recovery_epoch_index');
            $table->dropColumn([
                'watermark_at_epoch',
                'initial_watermark_at_epoch',
                'last_started_at_epoch',
                'last_succeeded_at_epoch',
                'last_error_at_epoch',
                'recovery_required_at_epoch',
                'recovery_from_at_epoch',
                'recovery_until_at_epoch',
                'last_recovery_succeeded_at_epoch',
                'last_recovered_from_at_epoch',
                'last_recovered_until_at_epoch',
            ]);
        });
    }
};
