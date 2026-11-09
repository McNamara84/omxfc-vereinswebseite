<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonSnapshotRepositoryTestFixtures
{
    use RefreshDatabase;

    private function datasetJson(?string $cycle): string
    {
        return (string) json_encode([['nummer' => 1, 'titel' => 'Roman', 'zyklus' => $cycle]], JSON_THROW_ON_ERROR);
    }
}
