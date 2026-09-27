<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_combats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('status', 30)->default('challenged');
            $table->string('rule_version', 60);
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('distance');
            $table->unsignedInteger('round_limit');
            $table->json('state')->nullable();
            $table->unsignedTinyInteger('winner_side')->nullable();
            $table->string('result', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['team_id', 'status']);
        });
        Schema::create('rpg_combat_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('side');
            $table->foreignId('rpg_character_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('character_revision');
            $table->string('character_name');
            $table->string('snapshot_hash', 64);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['rpg_combat_id', 'side']);
            $table->unique(['rpg_combat_id', 'rpg_character_id'], 'combat_character_unique');
            $table->index(['owner_id', 'rpg_combat_id']);
        });
        Schema::create('rpg_combat_character_locks', function (Blueprint $table): void {
            $table->foreignId('rpg_character_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('rpg_combat_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('token');
            $table->unsignedTinyInteger('side');
            $table->unsignedTinyInteger('controller_side')->nullable();
            $table->string('type', 40);
            $table->string('status', 20)->default('pending');
            $table->json('context');
            $table->timestamp('opened_at');
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('remaining_seconds')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('automatic')->default(false);
            $table->json('response')->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->unique(['rpg_combat_id', 'token']);
            $table->index(['status', 'due_at']);
        });
        Schema::create('rpg_combat_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('round');
            $table->string('kind', 50);
            $table->unsignedTinyInteger('side')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin', 20);
            $table->json('data');
            $table->timestamp('created_at');
            $table->unique(['rpg_combat_id', 'sequence']);
        });
        Schema::create('rpg_combat_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('input_hash', 64);
            $table->timestamp('created_at');
        });
        Schema::create('rpg_combat_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('challenger_name');
            $table->string('defender_name');
            $table->string('challenger_character');
            $table->string('defender_character');
            $table->string('result', 100)->nullable();
            $table->unsignedTinyInteger('winner_side')->nullable();
            $table->timestamps();
            $table->unique(['rpg_combat_id', 'kind']);
        });
        Schema::create('rpg_combat_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_combat_decision_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_key')->unique();
            $table->string('kind', 30);
            $table->string('status', 20)->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['status', 'claimed_at']);
        });
    }

    public function down(): void
    {
        foreach (['rpg_combat_deliveries', 'rpg_combat_milestones', 'rpg_combat_commands', 'rpg_combat_events',
            'rpg_combat_decisions', 'rpg_combat_character_locks', 'rpg_combat_participants', 'rpg_combats'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
