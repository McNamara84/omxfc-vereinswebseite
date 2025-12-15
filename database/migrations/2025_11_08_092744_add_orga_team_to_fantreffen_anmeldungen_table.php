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
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->boolean('orga_team')->default(false)->after('ist_mitglied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropColumn('orga_team');
        });
    }
};
