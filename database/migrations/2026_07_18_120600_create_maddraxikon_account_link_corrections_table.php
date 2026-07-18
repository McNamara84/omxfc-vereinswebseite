<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_account_link_corrections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedBigInteger('affected_user_id');
            $table->unsignedBigInteger('released_account_link_id');
            $table->unique('released_account_link_id', 'maddrax_corrections_link_unique');
            $table->string('wiki_key', 64);
            $table->char('old_oauth_subject_hash', 64);
            $table->unsignedBigInteger('old_wiki_user_id');
            $table->string('old_wiki_username');
            $table->text('reason');
            $table->timestamp('corrected_at');

            /*
             * Deliberately no foreign keys: deleting either local user must not
             * remove or mutate this security-relevant audit record.
             */
            $table->index(
                ['actor_user_id', 'corrected_at'],
                'maddraxikon_corrections_actor_time_index'
            );
            $table->index(
                ['affected_user_id', 'corrected_at'],
                'maddraxikon_corrections_affected_time_index'
            );
            $table->index(
                ['wiki_key', 'old_wiki_user_id'],
                'maddraxikon_corrections_wiki_user_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_account_link_corrections');
    }
};
