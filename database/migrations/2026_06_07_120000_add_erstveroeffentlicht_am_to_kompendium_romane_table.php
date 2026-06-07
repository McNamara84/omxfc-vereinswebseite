<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kompendium_romane', function (Blueprint $table) {
            $table->date('erstveroeffentlicht_am')->nullable()->after('zyklus');
            $table->index(['status', 'serie', 'erstveroeffentlicht_am'], 'komp_romane_status_serie_evt_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kompendium_romane', function (Blueprint $table) {
            $table->dropIndex('komp_romane_status_serie_evt_idx');
            $table->dropColumn('erstveroeffentlicht_am');
        });
    }
};
