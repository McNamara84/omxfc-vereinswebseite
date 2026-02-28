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
        Schema::table('downloads', function (Blueprint $table) {
            $table->string('slug')->unique()->after('title');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->unique('download_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropUnique(['download_id']);
        });

        Schema::table('downloads', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
