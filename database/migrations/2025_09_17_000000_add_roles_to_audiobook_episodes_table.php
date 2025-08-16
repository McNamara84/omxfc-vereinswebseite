<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audiobook_episodes', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_roles')->default(0);
            $table->unsignedSmallInteger('filled_roles')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('audiobook_episodes', function (Blueprint $table) {
            $table->dropColumn(['total_roles', 'filled_roles']);
        });
    }
};
