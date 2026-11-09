<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;

pest()->extend(BaseTestCase::class)->use(AlertComponentTestFixtures::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
});

test('alert renders title slot and icon without description', function (): void {
    $html = Blade::render('<x-alert title="Karte noch nicht verfügbar" icon="o-lock-closed">Freischaltung erst nach der ersten erledigten Aufgabe.</x-alert>');
    expect($html)->toContain('Karte noch nicht verfügbar')
        ->toContain('Freischaltung erst nach der ersten erledigten Aufgabe.')
        ->toContain('font-bold')
        ->and(str_contains($html, 'data-icon-name="o-lock-closed"') || str_contains($html, 'data-slot="icon"'))->toBeTrue()
        ->and($html)->not->toContain('•');
});

test('alert renders description and slot together', function (): void {
    $html = Blade::render('<x-alert title="Hinweis" description="Kurzbeschreibung">Zusätzlicher Hinweistext.</x-alert>');
    expect($html)->toContain('Hinweis')
        ->toContain('Kurzbeschreibung')
        ->toContain('Zusätzlicher Hinweistext.');
});

test('alert does not duplicate passed attributes', function (): void {
    $html = Blade::render('<x-alert id="warnung" data-testid="alert-komponente">Testinhalt</x-alert>');
    expect(substr_count($html, 'id="warnung"'))->toBe(1)
        ->and(substr_count($html, 'data-testid="alert-komponente"'))->toBe(1);
});
