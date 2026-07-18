<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_identity_tombstones', function (Blueprint $table): void {
            $table->id();
            $table->string('wiki_key', 64);
            $table->char('oauth_subject_hash', 64);
            $table->char('wiki_user_id_hash', 64);
            $table->timestamp('retired_at');
            $table->timestamps();

            $table->unique(
                ['wiki_key', 'oauth_subject_hash'],
                'maddraxikon_tombstones_wiki_subject_unique'
            );
            $table->unique(
                ['wiki_key', 'wiki_user_id_hash'],
                'maddraxikon_tombstones_wiki_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_identity_tombstones');
    }
};
