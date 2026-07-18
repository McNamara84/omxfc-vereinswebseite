<?php

use App\Enums\MaddraxikonAccountLinkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_account_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('wiki_key', 64);
            $table->string('oauth_subject', 191);
            $table->unsignedBigInteger('wiki_user_id');
            $table->string('wiki_username');
            $table->string('status', 32)->default(MaddraxikonAccountLinkStatus::Active->value);
            $table->string('verification_method', 32)->default('oauth2');
            $table->timestamp('first_verified_at');
            $table->timestamp('verified_at');
            $table->timestamp('disconnected_at')->nullable();
            $table->string('consent_version', 64);
            $table->timestamp('consented_at');
            $table->timestamps();

            // These constraints are intentionally retained after disconnecting.
            // They make both OAuth's opaque subject and MediaWiki's local ID
            // historical one-to-one identities, not reusable usernames.
            $table->unique(
                ['wiki_key', 'oauth_subject'],
                'maddraxikon_links_wiki_subject_unique'
            );
            $table->unique(
                ['wiki_key', 'wiki_user_id'],
                'maddraxikon_links_wiki_user_unique'
            );
            $table->index(['status', 'verified_at'], 'maddraxikon_links_status_verified_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_account_links');
    }
};
