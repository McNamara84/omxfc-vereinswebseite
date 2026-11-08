<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_item_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40);
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('revision')->default(0);
            $table->boolean('starting_equipment')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
        Schema::create('rpg_ammunition_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('name');
            $table->string('unit', 40);
        });
        Schema::create('rpg_item_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_type_id')->constrained('rpg_item_types')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('reference', 100)->unique();
            $table->json('definition');
            $table->json('input');
            $table->json('calculation');
            $table->string('rule_version', 80);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key')->unique();
            $table->char('submission_hash', 64);
            $table->timestamp('created_at');
            $table->unique(['item_type_id', 'version']);
        });
    }

    public function down(): void
    {
        if (DB::table('rpg_item_types')->exists() || DB::table('rpg_ammunition_profiles')->exists()) {
            throw new RuntimeException('Gespeicherte RPG-Gegenstandsversionen müssen erhalten bleiben.');
        }
        Schema::dropIfExists('rpg_item_versions');
        Schema::dropIfExists('rpg_ammunition_profiles');
        Schema::dropIfExists('rpg_item_types');
    }
};
