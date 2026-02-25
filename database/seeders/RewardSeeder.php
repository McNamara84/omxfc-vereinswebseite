<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RewardSeeder extends Seeder
{
    /**
     * Migrate rewards from config/rewards.php into the rewards table.
     * Automatically derives category from the reward title.
     * Safe to run multiple times (upserts by slug).
     */
    public function run(): void
    {
        $rewards = config('rewards', []);

        $sortOrder = 0;
        foreach ($rewards as $reward) {
            $title = $reward['title'];
            $slug = Str::slug($title);
            $category = $this->deriveCategory($title);

            Reward::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'description' => $reward['description'],
                    'category' => $category,
                    'cost_baxx' => $reward['points'],
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }

    /**
     * Derive the category from the reward title prefix.
     */
    private function deriveCategory(string $title): string
    {
        return match (true) {
            str_starts_with($title, 'Statistik') => 'Statistiken',
            str_starts_with($title, 'Downloads') => 'Downloads',
            str_starts_with($title, 'Maddraxiversum') => 'Maddraxiversum',
            str_starts_with($title, 'Kompendium') => 'Kompendium',
            str_starts_with($title, 'Mitgliederkarte') => 'Allgemein',
            default => 'Allgemein',
        };
    }
}
