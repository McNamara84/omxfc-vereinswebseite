<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Runner\ErrorHandler;

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
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        // Cache leeren, um sicherzustellen, dass Tests isoliert laufen
        Cache::flush();

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

    protected function tearDown(): void
    {
        parent::tearDown();

        // Workaround für Laravel/PHPUnit Handler-Tracking-Issue auf PHP 8.5:
        // HandleExceptions::flushHandlersState() nutzt get_error_handler() und
        // get_exception_handler() (PHP 8.5), die in manchen Builds fehlerhaft null
        // zurückgeben. Ohne Cleanup wachsen die Handler-Stacks mit jedem Test,
        // PHPUnit markiert alle Tests als risky → Failure bei failOnRisky=true.
        // Siehe: https://github.com/laravel/framework/issues/49502
        $this->ensureHandlerStackClean();
    }

    /**
     * Stellt sicher, dass der Error-/Exception-Handler-Stack nach tearDown
     * im erwarteten Zustand ist (1 Error-Handler = PHPUnit, 0 Exception-Handler).
     *
     * Nutzt die klassische set_*_handler()/restore_*_handler()-Technik, die in
     * allen PHP-Versionen funktioniert — unabhängig von get_*_handler().
     */
    private function ensureHandlerStackClean(): void
    {
        // Alle Exception-Handler entfernen (nach tearDown sollten 0 übrig sein)
        while (true) {
            $previous = set_exception_handler(static fn (\Throwable $e) => null);
            restore_exception_handler();
            if ($previous === null) {
                break;
            }
            restore_exception_handler();
        }

        // Alle Error-Handler entfernen, PHPUnits ErrorHandler merken
        $phpunitHandler = null;
        while (true) {
            $handler = set_error_handler(static fn () => false);
            restore_error_handler();
            if ($handler === null) {
                break;
            }
            restore_error_handler();
            if ($handler instanceof ErrorHandler) {
                $phpunitHandler = $handler;
            }
        }

        // PHPUnits Error-Handler wieder installieren
        if ($phpunitHandler !== null) {
            set_error_handler($phpunitHandler);
        }
    }
}
