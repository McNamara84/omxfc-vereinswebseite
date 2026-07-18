<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
            $table->unsignedBigInteger('occurred_at_epoch')
                ->nullable()
                ->after('occurred_at');
            $table->unsignedBigInteger('eligible_after_epoch')
                ->nullable()
                ->after('eligible_after');

            $table->index(
                ['status', 'eligible_after_epoch'],
                'maddraxikon_contributions_due_epoch_index',
            );
            $table->index(
                ['account_link_id', 'page_id', 'occurred_at_epoch'],
                'maddraxikon_contributions_session_epoch_index',
            );
        });
    }

    public function down(): void
    {
        /*
         * MariaDB may discard InnoDB's implicit account_link_id index once
         * the composite epoch index can support the foreign key. Recreate a
         * durable single-column index before dropping that composite index.
         */
        if (! Schema::hasIndex(
            'maddraxikon_contributions',
            'maddraxikon_contributions_account_link_index',
        )) {
            Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
                $table->index('account_link_id', 'maddraxikon_contributions_account_link_index');
            });
        }

        Schema::table('maddraxikon_contributions', function (Blueprint $table): void {
            $table->dropIndex('maddraxikon_contributions_due_epoch_index');
            $table->dropIndex('maddraxikon_contributions_session_epoch_index');
            $table->dropColumn([
                'occurred_at_epoch',
                'eligible_after_epoch',
            ]);
        });
    }
};
