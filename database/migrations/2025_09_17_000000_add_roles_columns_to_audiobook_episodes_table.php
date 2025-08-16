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
        Schema::table('audiobook_episodes', function (Blueprint $table) {
            $table->unsignedSmallInteger('roles_total')->default(0);
            $table->unsignedSmallInteger('roles_filled')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiobook_episodes', function (Blueprint $table) {
            $table->dropColumn(['roles_total', 'roles_filled']);
        });
    }
};
