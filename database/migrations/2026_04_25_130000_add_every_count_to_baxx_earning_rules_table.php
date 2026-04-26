<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baxx_earning_rules', function (Blueprint $table) {
            $table->unsignedInteger('every_count')->default(1)->after('points');
        });

        DB::table('baxx_earning_rules')
            ->whereIn('action_key', ['rezension', 'romantausch_offer'])
            ->update(['every_count' => 10]);
    }

    public function down(): void
    {
        Schema::table('baxx_earning_rules', function (Blueprint $table) {
            $table->dropColumn('every_count');
        });
    }
};