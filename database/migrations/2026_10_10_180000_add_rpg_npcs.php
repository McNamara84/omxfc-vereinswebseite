<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_npcs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('template_key', 40);
            $table->string('special_template_key', 40)->nullable();
            $table->string('template_version', 60);
            $table->string('rulebook_sha256', 64);
            $table->unsignedTinyInteger('source_page');
            $table->string('custom_name')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->json('configuration');
            $table->json('profile');
            $table->string('profile_hash', 64);
            $table->timestamps();
            $table->unique(['team_id', 'special_template_key']);
        });
        Schema::table('rpg_combats', function (Blueprint $table): void {
            $table->string('kind', 30)->default('player_vs_player');
            $table->json('suspension')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('rpg_combat_participants', function (Blueprint $table): void {
            $table->string('participant_kind', 10)->default('player');
            $table->foreignId('rpg_npc_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['rpg_combat_id', 'rpg_npc_id']);
        });
        // Deleted historical participants may have a null entity; mixed identities never may.
        $valid = "(participant_kind = 'npc' AND rpg_character_id IS NULL AND owner_id IS NULL) OR (participant_kind = 'player' AND rpg_npc_id IS NULL)";
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            $newValid = str_replace(['participant_kind', 'rpg_character_id', 'owner_id', 'rpg_npc_id'], ['NEW.participant_kind', 'NEW.rpg_character_id', 'NEW.owner_id', 'NEW.rpg_npc_id'], $valid);
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = 'CREATE TRIGGER rpg_participant_identity_'.strtolower($operation)." BEFORE $operation ON rpg_combat_participants";
                // MariaDB rejects CHECK conditions involving ON DELETE SET NULL foreign keys.
                if (DB::getDriverName() === 'sqlite') {
                    DB::statement($trigger." WHEN NOT ($newValid) BEGIN SELECT RAISE(ABORT, 'Invalid RPG participant identity'); END");
                } else {
                    DB::statement($trigger." FOR EACH ROW BEGIN IF NOT ($newValid) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid RPG participant identity'; END IF; END");
                }
            }
        }
        Schema::create('rpg_combat_npc_locks', function (Blueprint $table): void {
            $table->foreignId('rpg_npc_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('rpg_combat_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('rpg_combat_decisions', function (Blueprint $table): void {
            $table->string('timeout_policy', 20)->default('automatic');
            $table->timestamp('reminder_at')->nullable()->index();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedInteger('remaining_reminder_seconds')->nullable();
        });
        Schema::table('rpg_combat_milestones', fn (Blueprint $table) => $table->string('challenger_kind', 10)->default('player'));
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            DB::statement('DROP TRIGGER IF EXISTS rpg_participant_identity_insert');
            DB::statement('DROP TRIGGER IF EXISTS rpg_participant_identity_update');
        }
        Schema::table('rpg_combat_milestones', fn (Blueprint $table) => $table->dropColumn('challenger_kind'));
        Schema::table('rpg_combat_decisions', function (Blueprint $table): void {
            $table->dropIndex(['reminder_at']);
            $table->dropColumn(['timeout_policy', 'reminder_at', 'reminded_at', 'remaining_reminder_seconds']);
        });
        Schema::dropIfExists('rpg_combat_npc_locks');
        Schema::table('rpg_combat_participants', function (Blueprint $table): void {
            $table->dropUnique(['rpg_combat_id', 'rpg_npc_id']);
            $table->dropConstrainedForeignId('rpg_npc_id');
            $table->dropColumn('participant_kind');
        });
        Schema::table('rpg_combats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leader_id');
            $table->dropColumn(['kind', 'suspension']);
        });
        Schema::dropIfExists('rpg_npcs');
    }
};
