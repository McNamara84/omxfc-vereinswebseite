<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slug = 'kompendium';

        if (DB::table('rewards')->where('slug', $slug)->exists()) {
            return;
        }

        DB::table('rewards')->insert([
            'title' => 'Maddrax-Kompendium',
            'description' => 'Schaltet den Zugang zum Maddrax-Kompendium frei – eine umfassende Suchmaschine für das gesamte Maddrax-Universum.',
            'category' => 'Kompendium',
            'slug' => $slug,
            'cost_baxx' => config('rewards.kompendium_default_cost_baxx', 100),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rewards')
            ->where('slug', 'kompendium')
            ->delete();
    }
};
