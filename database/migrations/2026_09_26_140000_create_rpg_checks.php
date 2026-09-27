<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_check_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->text('description');
            $table->string('visibility', 10);
            $table->string('rule_version', 50);
            $table->timestamps();
            $table->index(['team_id', 'created_at']);
        });
        Schema::create('rpg_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_check_batch_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10);
            $table->integer('difficulty')->nullable();
            $table->string('status', 25)->default('pending');
            $table->unsignedTinyInteger('winner_position')->nullable();
            $table->integer('comparison_margin')->nullable();
            $table->string('resolution', 10)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('rpg_check_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpg_check_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('rpg_character_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('character_name');
            $table->unsignedInteger('character_revision');
            $table->string('check_type', 10);
            $table->string('attribute_key', 2);
            $table->string('skill_name')->nullable();
            $table->integer('attribute_value');
            $table->integer('skill_value')->nullable();
            $table->json('modifiers');
            $table->integer('modifier_total');
            $table->integer('base_total');
            foreach (['die_one', 'die_two', 'raw_total', 'total', 'margin'] as $name) {
                $table->integer($name)->nullable();
            }
            $table->string('result_kind', 25)->nullable();
            $table->foreignId('rolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_at')->nullable();
            $table->timestamps();
            $table->unique(['rpg_check_id', 'position'], 'rpg_check_position_unique');
            $table->unique(['rpg_check_id', 'rpg_character_id'], 'rpg_check_character_unique');
            $table->index(['owner_id', 'rpg_check_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpg_check_participants');
        Schema::dropIfExists('rpg_checks');
        Schema::dropIfExists('rpg_check_batches');
    }
};
