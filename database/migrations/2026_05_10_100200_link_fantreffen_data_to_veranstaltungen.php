<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->foreignId('veranstaltung_id')->nullable()->after('id')->constrained('veranstaltungen')->nullOnDelete();
        });

        Schema::table('fantreffen_vip_authors', function (Blueprint $table) {
            $table->foreignId('veranstaltung_id')->nullable()->after('id')->constrained('veranstaltungen')->nullOnDelete();
        });

        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');

        if ($archivEventId) {
            DB::table('fantreffen_anmeldungen')
                ->whereNull('veranstaltung_id')
                ->update(['veranstaltung_id' => $archivEventId]);

            DB::table('fantreffen_vip_authors')
                ->whereNull('veranstaltung_id')
                ->update(['veranstaltung_id' => $archivEventId]);
        }

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropUnique('fantreffen_anmeldungen_email_unique');
            $table->dropUnique('fantreffen_anmeldungen_user_id_unique');
            $table->unique(['veranstaltung_id', 'email']);
            $table->unique(['veranstaltung_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropUnique(['veranstaltung_id', 'email']);
            $table->dropUnique(['veranstaltung_id', 'user_id']);
            $table->unique('email');
            $table->unique('user_id');
        });

        Schema::table('fantreffen_vip_authors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('veranstaltung_id');
        });

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('veranstaltung_id');
        });
    }
};