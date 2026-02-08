<?php

namespace Database\Seeders;

use App\Models\FantreffenVipAuthor;
use Illuminate\Database\Seeder;

class FantreffenPlaywrightSeeder extends Seeder
{
    /**
     * Seed VIP-Autoren für Playwright-E2E-Tests.
     */
    public function run(): void
    {
        FantreffenVipAuthor::create([
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'is_tentative' => false,
            'sort_order' => 1,
        ]);

        FantreffenVipAuthor::create([
            'name' => 'Jo Zybell',
            'pseudonym' => null,
            'is_active' => true,
            'is_tentative' => true,
            'sort_order' => 5,
        ]);
    }
}
