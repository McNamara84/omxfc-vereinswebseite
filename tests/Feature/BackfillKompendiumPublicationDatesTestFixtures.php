<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait BackfillKompendiumPublicationDatesTestFixtures
{
    use RefreshDatabase;

    private function bindKompendiumMetadata(array $seriesByKey): void
    {
        $maddraxDataService = $this->createStub(MaddraxDataService::class);
        $maddraxDataService->method('getSeries')->willReturnCallback(fn (string $key) => collect($seriesByKey[$key] ?? []));
        $this->app->instance(KompendiumService::class, new KompendiumService($maddraxDataService));
    }
}
