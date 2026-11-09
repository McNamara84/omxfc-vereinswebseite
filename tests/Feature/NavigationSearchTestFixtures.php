<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Tests\Concerns\CreatesUserWithRole;

trait NavigationSearchTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function assertPrivateSearchHeaders(ResponseHeaderBag $headers): void
    {
        // Livewire may add stricter directives; the privacy requirements must remain intact.
        expect($headers->hasCacheControlDirective('private'))->toBeTrue();
        expect($headers->hasCacheControlDirective('no-store'))->toBeTrue()
            ->and($headers->getCacheControlDirective('max-age'))->toBe('0')
            ->and($headers->hasCacheControlDirective('public'))->toBeFalse();
    }
}
