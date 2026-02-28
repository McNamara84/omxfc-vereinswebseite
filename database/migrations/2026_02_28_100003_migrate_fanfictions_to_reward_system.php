<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultCost = (int) config('rewards.fanfiction_default_cost_baxx', 5);

        DB::table('fanfictions')
            ->where('status', 'published')
            ->whereNull('reward_id')
            ->orderBy('id')
            ->chunkById(100, function ($fanfictions) use ($defaultCost) {
                foreach ($fanfictions as $fanfiction) {
                    $baseSlug = Str::slug($fanfiction->title);
                    $slug = 'fanfiction-'.$baseSlug;
                    $counter = 2;

                    while (DB::table('rewards')->where('slug', $slug)->exists()) {
                        $slug = 'fanfiction-'.$baseSlug.'-'.$counter;
                        $counter++;
                    }

                    // Markdown rendern, dann HTML-Tags entfernen für sauberen Plaintext-Teaser
                    $html = Str::markdown($fanfiction->content ?? '');
                    $plainText = strip_tags($html);
                    $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
                    $plainText = preg_replace('/\s+/', ' ', trim($plainText));
                    $teaser = Str::limit($plainText, 200);

                    $rewardId = DB::table('rewards')->insertGetId([
                        'title' => $fanfiction->title,
                        'description' => $teaser,
                        'category' => 'Fanfiction',
                        'slug' => $slug,
                        'cost_baxx' => $defaultCost,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('fanfictions')
                        ->where('id', $fanfiction->id)
                        ->update(['reward_id' => $rewardId]);
                }
            });
    }

    public function down(): void
    {
        // Nur reward_id zurücksetzen und Rewards löschen, die von dieser Migration stammen
        $rewardIds = DB::table('fanfictions')
            ->whereNotNull('reward_id')
            ->pluck('reward_id');

        DB::table('fanfictions')
            ->whereNotNull('reward_id')
            ->update(['reward_id' => null]);

        DB::table('rewards')
            ->whereIn('id', $rewardIds)
            ->where('category', 'Fanfiction')
            ->delete();
    }
};
