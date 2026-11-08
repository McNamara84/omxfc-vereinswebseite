<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\Controllers\KompendiumController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;

trait KompendiumPathValidationTestFixtures
{
    use RefreshDatabase;

    private KompendiumController $controller;

    /**
     * Zugriff auf private Methode isValidRomanPath() via Reflection
     */
    private function isValidRomanPath(string $path): bool
    {
        $method = new ReflectionMethod(KompendiumController::class, 'isValidRomanPath');

        return $method->invoke($this->controller, $path);
    }

    private function expectRejectedPathWarning(): void
    {
        Log::shouldReceive('channel')->zeroOrMoreTimes()->andReturnSelf();
        Log::shouldReceive('warning')->atLeast()->once();
    }
}
