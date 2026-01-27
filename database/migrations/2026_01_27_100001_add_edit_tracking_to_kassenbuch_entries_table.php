<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Diese Migration fügt nur neue nullable Spalten hinzu.
     * Bestehende Kassenbucheinträge bleiben unverändert erhalten.
     */
    public function up(): void
    {
        Schema::table('kassenbuch_entries', function (Blueprint $table) {
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable();
            $table->text('last_edit_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kassenbuch_entries', function (Blueprint $table) {
            $table->dropForeign(['last_edited_by']);
            $table->dropColumn(['last_edited_by', 'last_edited_at', 'last_edit_reason']);
        });
    }
};
