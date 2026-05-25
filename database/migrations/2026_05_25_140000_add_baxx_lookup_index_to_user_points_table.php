<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_points', function (Blueprint $table) {
            $table->index(['user_id', 'team_id', 'created_at', 'id'], 'user_points_baxx_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_points', function (Blueprint $table) {
            $table->dropIndex('user_points_baxx_lookup_index');
        });
    }
};