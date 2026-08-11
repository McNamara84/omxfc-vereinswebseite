<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_rating_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('wiki_key', 64)->unique();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_category')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('last_candidate_count')->default(0);
            $table->unsignedInteger('last_updated_count')->default(0);
            $table->unsignedInteger('last_removed_count')->default(0);
            $table->unsignedInteger('last_skipped_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_rating_sync_states');
    }
};
