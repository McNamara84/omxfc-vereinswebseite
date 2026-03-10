<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slug = 'kompendium';

        $data = [
            'title' => 'Maddrax-Kompendium',
            'description' => 'Schaltet den Zugang zum Maddrax-Kompendium frei – eine umfassende Suchmaschine für das gesamte Maddrax-Universum.',
            'category' => 'Kompendium',
            'cost_baxx' => config('rewards.kompendium_default_cost_baxx', 100),
            'is_active' => true,
            'sort_order' => 0,
            'updated_at' => now(),
        ];

        if (DB::table('rewards')->where('slug', $slug)->exists()) {
            DB::table('rewards')->where('slug', $slug)->update($data);
        } else {
            DB::table('rewards')->insert(array_merge(['slug' => $slug, 'created_at' => now()], $data));
        }
    }

    public function down(): void
    {
        DB::table('rewards')
            ->where('slug', 'kompendium')
            ->delete();
    }
};
