<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpg_characters', function (Blueprint $table): void {
            $table->json('initial_payload')->nullable();
            $table->unsignedInteger('revision')->default(0);
        });
        DB::table('rpg_characters')->orderBy('id')->chunkById(200, function ($characters): void {
            foreach ($characters as $character) {
                DB::table('rpg_characters')->where('id', $character->id)->update(['initial_payload' => $character->payload]);
            }
        });
        Schema::create('rpg_adventures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('title');
            $table->date('completed_on');
            $table->unsignedInteger('minutes');
            $table->unsignedTinyInteger('cycle_bonus');
            $table->string('rule_version', 50);
            $table->timestamps();
        });
        Schema::create('rpg_experience_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_adventure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_character_id')->constrained()->cascadeOnDelete();
            $table->string('character_name');
            $table->json('criteria');
            $table->unsignedInteger('calculated_points');
            $table->unsignedInteger('points');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['rpg_adventure_id', 'rpg_character_id'], 'rpg_award_adventure_character_unique');
        });
        Schema::create('rpg_advancement_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('revision');
            $table->string('rule_version', 50);
            $table->json('operations');
            $table->json('changes');
            $table->json('before_payload');
            $table->json('after_payload');
            $table->unsignedInteger('cost');
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['rpg_character_id', 'status']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('rpg_experience_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_experience_award_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_advancement_request_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamps();
            $table->index(['rpg_character_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpg_experience_entries');
        Schema::dropIfExists('rpg_advancement_requests');
        Schema::dropIfExists('rpg_experience_awards');
        Schema::dropIfExists('rpg_adventures');
        Schema::table('rpg_characters', fn (Blueprint $table) => $table->dropColumn(['initial_payload', 'revision']));
    }
};
