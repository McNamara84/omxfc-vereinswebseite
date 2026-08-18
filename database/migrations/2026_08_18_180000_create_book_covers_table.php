<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_covers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('source_file_title')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_sha1', 64)->nullable()->index();
            $table->text('source_description_url')->nullable();
            $table->text('source_artist')->nullable();
            $table->text('source_credit')->nullable();
            $table->string('source_license')->nullable();
            $table->text('source_license_url')->nullable();
            $table->string('small_path')->nullable();
            $table->string('large_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_covers');
    }
};
