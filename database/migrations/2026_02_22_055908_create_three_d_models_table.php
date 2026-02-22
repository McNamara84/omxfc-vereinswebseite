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
        Schema::create('three_d_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description');
            $table->string('file_path', 500);
            $table->string('file_format', 10);
            $table->unsignedBigInteger('file_size');
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('maddraxikon_url', 500)->nullable();
            $table->unsignedInteger('required_baxx');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('three_d_models');
    }
};
