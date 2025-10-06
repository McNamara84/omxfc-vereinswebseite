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
        if (Schema::hasTable('resource_types')) {
            return;
        }

        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->string('application');
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['application', 'slug']);
            $table->index('application');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_types');
    }
};
