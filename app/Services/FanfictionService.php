<?php

namespace App\Services;

use App\Models\Fanfiction;
use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Support\Str;

class FanfictionService
{
    /**
     * Erstellt einen Reward für eine veröffentlichte Fanfiction.
     */
    public function createRewardForFanfiction(Fanfiction $fanfiction, ?int $costBaxx = null): Reward
    {
        $costBaxx ??= (int) config('rewards.fanfiction_default_cost_baxx', 5);

        $baseSlug = Str::slug($fanfiction->title);
        $slug = 'fanfiction-'.$baseSlug;
        $counter = 2;

        while (Reward::where('slug', $slug)->exists()) {
            $slug = 'fanfiction-'.$baseSlug.'-'.$counter;
            $counter++;
        }

        $reward = Reward::create([
            'title' => $fanfiction->title,
            'description' => Str::limit($fanfiction->teaser, 200),
            'category' => 'Fanfiction',
            'slug' => $slug,
            'cost_baxx' => $costBaxx,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $fanfiction->update(['reward_id' => $reward->id]);

        return $reward;
    }

    /**
     * Aktualisiert den Reward einer Fanfiction.
     */
    public function updateRewardForFanfiction(Fanfiction $fanfiction, int $costBaxx): void
    {
        $reward = $fanfiction->reward;

        if (! $reward) {
            $this->createRewardForFanfiction($fanfiction, $costBaxx);

            return;
        }

        $reward->update([
            'title' => $fanfiction->title,
            'description' => Str::limit($fanfiction->teaser, 200),
            'cost_baxx' => $costBaxx,
        ]);
    }

    /**
     * Löscht oder deaktiviert den Reward einer Fanfiction.
     * Deaktiviert statt löschen, wenn aktive Käufe existieren.
     */
    public function deleteRewardForFanfiction(Fanfiction $fanfiction): void
    {
        $reward = $fanfiction->reward;

        if (! $reward) {
            return;
        }

        $hasActivePurchases = RewardPurchase::where('reward_id', $reward->id)
            ->active()
            ->exists();

        if ($hasActivePurchases) {
            $reward->update(['is_active' => false]);
        } else {
            $reward->delete();
        }
    }
}
