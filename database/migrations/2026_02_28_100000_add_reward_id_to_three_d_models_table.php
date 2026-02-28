<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('three_d_models', function (Blueprint $table) {
            $table->foreignId('reward_id')->nullable()->unique()->constrained('rewards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('three_d_models', function (Blueprint $table) {
            $table->dropForeign(['reward_id']);
            $table->dropColumn('reward_id');
        });
    }
};
