<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_character_renames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpg_character_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_purchase_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('old_name');
            $table->string('new_name');
            $table->unsignedInteger('revision_before');
            $table->unsignedInteger('revision_after');
            $table->uuid('submission_key')->unique();
            $table->char('submission_hash', 64);
            $table->timestamp('created_at');
            $table->index(['rpg_character_id', 'id']);
        });

        DB::table('rewards')->insertOrIgnore([
            'slug' => 'rpg-character-rename',
            'title' => 'RPG-Charakter umbenennen',
            'description' => 'System-Buchung für die Umbenennung eines eigenen gespeicherten RPG-Charakters.',
            'category' => 'System',
            'cost_baxx' => 25,
            'is_active' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('rpg_character_renames')->exists()
            || DB::table('reward_purchases')->whereIn('reward_id', DB::table('rewards')->select('id')->where('slug', 'rpg-character-rename'))->exists()) {
            throw new RuntimeException('Umbenennungen oder Käufe sind vorhanden. Namensnachweise und Baxx-Ausgaben müssen erhalten bleiben.');
        }

        Schema::dropIfExists('rpg_character_renames');
        DB::table('rewards')->where('slug', 'rpg-character-rename')->delete();
    }
};
