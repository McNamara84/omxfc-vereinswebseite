<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeriesProfileTestFixtures
{
    use RefreshDatabase;

    private function stubOptions(array $values = []): void
    {
        $options = array_fill_keys(array_diff(array_keys(SeriesProfileCompletion::FIELDS), ['lieblingsmutation']), []);
        $this->mock(SeriesProfileOptions::class)->shouldReceive('all')->andReturn(array_replace($options, $values));
    }

    private function catalog(array $rows): void
    {
        DB::transaction(fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate((string) Str::uuid(), ['maddrax' => json_encode($rows, JSON_THROW_ON_ERROR)]));
    }
}
