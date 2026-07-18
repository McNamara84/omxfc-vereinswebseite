<?php

use App\Enums\MaddraxikonContributionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_contributions', function (Blueprint $table): void {
            $table->id();
            $table->string('wiki_key', 64);
            $table->unsignedBigInteger('rc_id');
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('parent_revision_id')->nullable();
            $table->unsignedBigInteger('page_id');
            $table->integer('namespace_id');
            $table->string('page_title');
            $table->unsignedBigInteger('wiki_user_id');
            $table->string('wiki_username');
            $table->foreignId('account_link_id')
                ->nullable()
                ->constrained('maddraxikon_account_links')
                ->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->boolean('minor')->default(false);
            $table->boolean('bot')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->boolean('redirect')->default(false);
            $table->boolean('user_hidden')->default(false);
            $table->unsignedBigInteger('old_size')->nullable();
            $table->unsignedBigInteger('new_size')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('session_anchor_revision_id')->nullable();
            $table->string('status', 32)->default(MaddraxikonContributionStatus::Pending->value);
            $table->string('status_reason', 191)->nullable();
            $table->timestamp('eligible_after');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['wiki_key', 'revision_id'],
                'maddraxikon_contributions_wiki_revision_unique'
            );
            $table->unique(
                ['wiki_key', 'rc_id'],
                'maddraxikon_contributions_wiki_rc_unique'
            );
            $table->index(
                ['wiki_key', 'wiki_user_id', 'page_id', 'occurred_at'],
                'maddraxikon_contributions_session_index'
            );
            $table->index(
                ['status', 'eligible_after'],
                'maddraxikon_contributions_eligibility_index'
            );
            $table->index(
                ['user_id', 'occurred_at'],
                'maddraxikon_contributions_user_time_index'
            );
            $table->index(
                'account_link_id',
                'maddraxikon_contributions_account_link_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_contributions');
    }
};
