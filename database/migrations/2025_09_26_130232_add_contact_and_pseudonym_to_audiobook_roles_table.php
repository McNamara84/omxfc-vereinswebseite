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
        Schema::table('audiobook_roles', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('speaker_name');
            $table->string('speaker_pseudonym')->nullable()->after('contact_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiobook_roles', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'speaker_pseudonym']);
        });
    }
};
