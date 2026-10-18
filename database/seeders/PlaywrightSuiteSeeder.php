<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlaywrightSuiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RewardSeeder::class,
            BaxxEarningRuleSeeder::class,
            TodoCategorySeeder::class,
            TodoPlaywrightSeeder::class,
            DashboardSampleSeeder::class,
            ArbeitsgruppenPlaywrightSeeder::class,
            BookPlaywrightSeeder::class,
            FanfictionPlaywrightSeeder::class,
            KompendiumPlaywrightSeeder::class,
            FantreffenPlaywrightSeeder::class,
            CoverRatingPlaywrightSeeder::class,
            BildersuchePlaywrightSeeder::class,
            UxPreviewSeeder::class,
        ]);
    }
}
