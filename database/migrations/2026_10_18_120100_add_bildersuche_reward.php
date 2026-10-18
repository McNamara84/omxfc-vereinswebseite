<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('rewards')->where('slug', 'bildersuche')->exists()) {
            DB::table('rewards')->insert([
                'slug' => 'bildersuche',
                'title' => 'Maddrax-Bildersuche',
                'description' => 'Finde Cover anhand ihrer Maddraxikon-Schlagworte. Mitglieder der AG Maddraxikon erhalten automatisch Zugang.',
                'category' => 'Bildersuche',
                'cost_baxx' => 100,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve purchase history, including when rolling back this feature.
        DB::table('rewards')->where('slug', 'bildersuche')->update(['is_active' => false]);
    }
};
