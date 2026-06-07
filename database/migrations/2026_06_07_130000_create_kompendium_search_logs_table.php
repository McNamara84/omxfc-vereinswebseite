<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kompendium_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('query');
            $table->string('normalized_query', 255);
            $table->json('parsed_query')->nullable();
            $table->json('selected_serien')->nullable();
            $table->string('sort')->default('relevance');
            $table->string('direction')->default('desc');
            $table->unsignedInteger('results_count')->default(0);
            $table->string('source')->default('search_submit');
            $table->string('status')->default('ok');
            $table->boolean('is_admin_search')->default(false);
            $table->boolean('candidates_truncated')->default(false);
            $table->unsignedInteger('scanned_candidates')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['normalized_query', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['is_admin_search', 'created_at']);
            $table->index(['results_count', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kompendium_search_logs');
    }
};
