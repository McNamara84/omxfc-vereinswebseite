<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Default coordinates used for stubbed Nominatim responses (Munich).
     */
    protected const DEFAULT_LAT = '48.0';
    protected const DEFAULT_LON = '11.0';


    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => self::DEFAULT_LAT,
                'lon' => self::DEFAULT_LON,
            ]], 200),
        ]);

        // Seed the database so default entities such as the "Mitglieder" team
        // are available to the tests. Seeding occurs after HTTP calls are
        // faked to avoid real network requests.
        $this->seed();
    }
}
