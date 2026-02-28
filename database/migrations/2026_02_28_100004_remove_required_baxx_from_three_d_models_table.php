<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('three_d_models', function (Blueprint $table) {
            $table->dropColumn('required_baxx');
        });
    }

    public function down(): void
    {
        Schema::table('three_d_models', function (Blueprint $table) {
            $table->unsignedInteger('required_baxx');
        });
    }
};
