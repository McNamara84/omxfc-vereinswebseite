<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_cover_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'book_cover_id']);
            $table->index(['book_cover_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_ratings');
    }
};
