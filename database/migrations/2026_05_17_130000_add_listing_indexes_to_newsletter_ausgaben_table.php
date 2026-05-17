<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_ausgaben', function (Blueprint $table) {
            $table->index(
                ['status', 'published_at', 'sent_at'],
                'newsletter_ausgaben_status_published_at_sent_at_index'
            );
            $table->index(
                ['sent_at', 'created_at'],
                'newsletter_ausgaben_sent_at_created_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_ausgaben', function (Blueprint $table) {
            $table->dropIndex('newsletter_ausgaben_status_published_at_sent_at_index');
            $table->dropIndex('newsletter_ausgaben_sent_at_created_at_index');
        });
    }
};