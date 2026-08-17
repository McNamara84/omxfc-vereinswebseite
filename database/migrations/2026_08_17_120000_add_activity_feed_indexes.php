<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'activities_feed_cursor_index');
            $table->index(['subject_type', 'created_at', 'id'], 'activities_feed_filter_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('activities_feed_filter_cursor_index');
            $table->dropIndex('activities_feed_cursor_index');
        });
    }
};
