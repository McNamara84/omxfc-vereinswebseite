<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_cover_keywords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_cover_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $normalized = $table->string('normalized_keyword');
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $normalized->collation('utf8mb4_bin');
            }
            $table->unique(['book_cover_id', 'normalized_keyword']);
            $table->timestamps();
        });

        Schema::table('book_covers', function (Blueprint $table): void {
            $table->string('keywords_source_file_title')->nullable();
            $table->timestamp('keywords_synced_at')->nullable();
            $table->timestamp('keywords_sync_attempted_at')->nullable();
            $table->text('keywords_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_cover_keywords');
        Schema::table('book_covers', function (Blueprint $table): void {
            $table->dropColumn(['keywords_source_file_title', 'keywords_synced_at', 'keywords_sync_attempted_at', 'keywords_sync_error']);
        });
    }
};
