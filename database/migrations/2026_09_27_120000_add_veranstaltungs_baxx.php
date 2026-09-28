<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veranstaltungen', function (Blueprint $table) {
            $table->unsignedTinyInteger('teilnahme_baxx')->default(10);
            $table->string('baxx_status')->default('offen');
            $table->timestamp('baxx_abgeschlossen_am')->nullable();
            $table->foreignId('baxx_abgeschlossen_von')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('veranstaltungen')->where('status', 'archiviert')
            ->update(['baxx_status' => 'bestand_ausgeschlossen']);

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->boolean('teilgenommen')->default(false);
            $table->timestamp('teilnahme_bestaetigt_am')->nullable();
            $table->foreignId('teilnahme_bestaetigt_von')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('user_points', function (Blueprint $table) {
            $table->foreignId('veranstaltung_id')->nullable()->constrained('veranstaltungen')->restrictOnDelete();
            $table->unique(['veranstaltung_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_points', function (Blueprint $table) {
            $table->dropUnique(['veranstaltung_id', 'user_id']);
            $table->dropConstrainedForeignId('veranstaltung_id');
        });

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teilnahme_bestaetigt_von');
            $table->dropColumn(['teilgenommen', 'teilnahme_bestaetigt_am']);
        });

        Schema::table('veranstaltungen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('baxx_abgeschlossen_von');
            $table->dropColumn(['teilnahme_baxx', 'baxx_status', 'baxx_abgeschlossen_am']);
        });
    }
};
