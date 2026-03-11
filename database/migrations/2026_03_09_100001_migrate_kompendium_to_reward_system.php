<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newSlug = 'kompendium';
        $legacySlug = 'kompendium-suche';

        $data = [
            'slug' => $newSlug,
            'title' => 'Maddrax-Kompendium',
            'description' => 'Schaltet den Zugang zum Maddrax-Kompendium frei – eine umfassende Suchmaschine für das gesamte Maddrax-Universum.',
            'category' => 'Kompendium',
            'cost_baxx' => (int) config('rewards.kompendium_default_cost_baxx', 100),
            'is_active' => true,
            'sort_order' => 0,
            'updated_at' => now(),
        ];

        // Legacy-Reward umbenennen statt deaktivieren – so bleiben bestehende Käufe erhalten
        if (DB::table('rewards')->where('slug', $legacySlug)->exists()) {
            DB::table('rewards')->where('slug', $legacySlug)->update($data);
        } elseif (DB::table('rewards')->where('slug', $newSlug)->exists()) {
            DB::table('rewards')->where('slug', $newSlug)->update($data);
        } else {
            DB::table('rewards')->insert(array_merge(['created_at' => now()], $data));
        }
    }

    public function down(): void
    {
        // Reward zurückbenennen statt löschen – bewahrt Kaufhistorie (cascadeOnDelete)
        DB::table('rewards')
            ->where('slug', 'kompendium')
            ->update([
                'slug' => 'kompendium-suche',
                'title' => 'Kompendium-Suche',
                'category' => 'Kompendium',
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
