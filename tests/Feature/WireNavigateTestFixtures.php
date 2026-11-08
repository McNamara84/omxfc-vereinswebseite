<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait WireNavigateTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * Prüft, dass ein konkreter href-Wert zusammen mit wire:navigate im selben Tag auftaucht.
     */
    private function assertLinkHasWireNavigate(string $html, string $url, string $context = ''): void
    {
        $escaped = preg_quote($url, '/');
        // Entweder href="URL" ... wire:navigate oder wire:navigate ... href="URL"
        $pattern = '/<(a|button)[^>]*href="'.$escaped.'"[^>]*wire:navigate/s';
        $patternReverse = '/<(a|button)[^>]*wire:navigate[^>]*href="'.$escaped.'"/s';
        expect((bool) preg_match($pattern, $html) || (bool) preg_match($patternReverse, $html))->toBeTrue();
    }

    /**
     * Prüft, dass ein konkreter href-Wert KEIN wire:navigate hat.
     */
    private function assertLinkHasNoWireNavigate(string $html, string $url, string $context = ''): void
    {
        $escaped = preg_quote($url, '/');
        $pattern = '/<(a|button)[^>]*href="'.$escaped.'"[^>]*wire:navigate/s';
        $patternReverse = '/<(a|button)[^>]*wire:navigate[^>]*href="'.$escaped.'"/s';
        expect((bool) preg_match($pattern, $html) || (bool) preg_match($patternReverse, $html))->toBeFalse();
    }
}
