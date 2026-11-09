<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonStreakEpochBackfillTestFixtures
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_24_140000_backfill_maddraxikon_contribution_epochs.php');
    }
}
