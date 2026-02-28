<?php

use App\Enums\FanfictionStatus;
use App\Models\Fanfiction;
use App\Models\Reward;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultCost = (int) config('rewards.fanfiction_default_cost_baxx', 5);

        Fanfiction::where('status', FanfictionStatus::Published)
            ->whereNull('reward_id')
            ->chunkById(100, function ($fanfictions) use ($defaultCost) {
                foreach ($fanfictions as $fanfiction) {
                    $baseSlug = Str::slug($fanfiction->title);
                    $slug = 'fanfiction-'.$baseSlug;
                    $counter = 2;

                    while (Reward::where('slug', $slug)->exists()) {
                        $slug = 'fanfiction-'.$baseSlug.'-'.$counter;
                        $counter++;
                    }

                    $teaser = Str::limit($fanfiction->teaser, 200);

                    $reward = Reward::create([
                        'title' => $fanfiction->title,
                        'description' => $teaser,
                        'category' => 'Fanfiction',
                        'slug' => $slug,
                        'cost_baxx' => $defaultCost,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]);

                    $fanfiction->update(['reward_id' => $reward->id]);
                }
            });
    }

    public function down(): void
    {
        // Verknüpfte Rewards der Fanfictions entfernen
        $rewardIds = Fanfiction::whereNotNull('reward_id')->pluck('reward_id');
        Fanfiction::whereNotNull('reward_id')->update(['reward_id' => null]);
        Reward::whereIn('id', $rewardIds)->delete();
    }
};
