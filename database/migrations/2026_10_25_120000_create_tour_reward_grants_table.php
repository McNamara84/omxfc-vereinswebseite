<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reward_key');
            $table->foreignId('tour_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tour_key');
            $table->unsignedInteger('tour_version');
            $table->foreignId('user_point_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('points');
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'reward_key']);
        });
    }

    public function down(): void
    {
        if (DB::table('tour_reward_grants')->exists()) {
            throw new RuntimeException('Tour-Vergaben sind vorhanden. Einmaligkeitsnachweise dürfen nicht entfernt werden.');
        }

        Schema::dropIfExists('tour_reward_grants');
    }
};
