<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxDataServiceTestFixtures
{
    use RefreshDatabase;

    private MaddraxDataService $service;

    private string $testStoragePath;

    private function createMaddraxFile(array $data = []): void
    {
        $defaultData = [['nummer' => 1, 'titel' => 'Erster Roman', 'text' => ['Autor1'], 'zyklus' => 'Euree', 'bewertung' => 4.0, 'stimmen' => 10], ['nummer' => 2, 'titel' => 'Zweiter Roman', 'text' => ['Autor1', 'Autor2'], 'zyklus' => 'Euree', 'bewertung' => 5.0, 'stimmen' => 20]];
        $path = storage_path('app/private/maddrax.json');
        file_put_contents($path, json_encode($data ?: $defaultData));
    }

    private function createSeriesFile(string $filename, array $data = []): void
    {
        $path = storage_path('app/private/'.$filename);
        file_put_contents($path, json_encode($data));
    }
}
