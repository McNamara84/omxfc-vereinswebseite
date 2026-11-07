<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->string('submission_hash', 64);
            $table->string('kind', 30);
            $table->text('reason');
            $table->date('occurred_on');
            $table->string('catalog_version', 60);
            $table->json('details');
            $table->foreignId('reversal_of')->nullable()->unique()->constrained('rpg_inventory_transactions')->restrictOnDelete();
            $table->foreignId('advancement_id')->nullable()->unique()->constrained('rpg_advancement_requests')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['team_id', 'id']);
        });
        Schema::create('rpg_inventory_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('rpg_inventory_transactions')->cascadeOnDelete();
            $table->foreignId('rpg_character_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_before');
            $table->unsignedInteger('revision_after');
            $table->json('before_state');
            $table->json('after_state');
            $table->json('changes');
            $table->unique(['transaction_id', 'rpg_character_id'], 'rpg_inventory_transaction_character_unique');
            $table->index(['rpg_character_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('rpg_inventory_transactions')->exists()) {
            throw new LogicException('Inventarbuchungen müssen bei einem Rückbau erhalten bleiben.');
        }
        Schema::dropIfExists('rpg_inventory_entries');
        Schema::dropIfExists('rpg_inventory_transactions');
    }
};
