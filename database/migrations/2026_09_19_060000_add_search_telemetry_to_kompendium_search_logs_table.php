<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kompendium_search_logs', function (Blueprint $table) {
            $table->string('search_mode', 20)->default('lexical')->after('status');
            $table->unsignedInteger('duration_ms')->nullable()->after('search_mode');
            $table->index(['search_mode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('kompendium_search_logs', function (Blueprint $table) {
            $table->dropIndex(['search_mode', 'created_at']);
            $table->dropColumn(['search_mode', 'duration_ms']);
        });
    }
};
