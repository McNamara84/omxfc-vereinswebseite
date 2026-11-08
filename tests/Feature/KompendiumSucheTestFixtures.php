<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

trait KompendiumSucheTestFixtures
{
    use RefreshDatabase;

    private function setupSearchMock(array $files, array $searchResultPaths): void
    {
        Storage::fake('private');
        foreach ($files as $path => $content) {
            Storage::disk('private')->put($path, $content);
        }
        $this->partialMock(KompendiumSearchService::class, function ($mock) use ($searchResultPaths) {
            $mock->shouldReceive('search')->andReturn(['ids' => $searchResultPaths]);
        });
    }
}
