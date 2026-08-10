<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_review_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_link_id')
                ->constrained('maddraxikon_account_links')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('maddraxikon_page_id')->index();
            $table->unsignedBigInteger('wiki_user_id')->index();
            $table->unsignedTinyInteger('rating');
            $table->timestamp('source_voted_at')->nullable();
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->index(
                ['wiki_user_id', 'maddraxikon_page_id'],
                'maddraxikon_review_ratings_source_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_review_ratings');
    }
};
