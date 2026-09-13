<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_book_snapshots', function (Blueprint $table): void {
            $table->uuid('snapshot_id');
            $table->string('series_key', 32);
            $table->longText('payload');
            $table->timestamp('created_at');
            $table->timestamp('retired_at')->nullable();
            $table->primary(['snapshot_id', 'series_key']);
            $table->index(['series_key', 'created_at']);
            $table->index('retired_at');
        });

        Schema::create('maddraxikon_book_snapshot_pointers', function (Blueprint $table): void {
            $table->string('series_key', 32)->primary();
            $table->uuid('snapshot_id');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_book_snapshot_pointers');
        Schema::dropIfExists('maddraxikon_book_snapshots');
    }
};
