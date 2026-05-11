<?php

namespace Database\Seeders;

use App\Models\FantreffenVipAuthor;
use App\Models\Veranstaltung;
use Illuminate\Database\Seeder;

class FantreffenPlaywrightSeeder extends Seeder
{
    /**
     * Seed VIP-Autoren für Playwright-E2E-Tests.
     */
    public function run(): void
    {
        $veranstaltungId = Veranstaltung::query()
            ->where('slug', 'maddrax-fantreffen-2026')
            ->value('id');

        FantreffenVipAuthor::create([
            'veranstaltung_id' => $veranstaltungId,
            'name' => 'Oliver Fröhlich',
            'pseudonym' => 'Ian Rolf Hill',
            'is_active' => true,
            'is_tentative' => false,
            'sort_order' => 0,
        ]);

        FantreffenVipAuthor::create([
            'veranstaltung_id' => $veranstaltungId,
            'name' => 'Jo Zybell',
            'pseudonym' => null,
            'is_active' => true,
            'is_tentative' => true,
            'sort_order' => 1,
        ]);
    }
}
