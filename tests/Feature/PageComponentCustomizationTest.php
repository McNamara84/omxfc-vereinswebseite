<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(PageComponentCustomizationTestFixtures::class);

test('public page component allows extra classes', function (): void {
    $this->assertAllowsExtraClasses('x-public-page', 'text-red-500', 'max-w-6xl');
});

test('member page component allows extra classes', function (): void {
    $this->assertAllowsExtraClasses('x-member-page', 'p-2', 'max-w-7xl');
});

test('bento card renders default slot only once without value or description slot', function (): void {
    $html = Blade::render('<x-bento-card title="Testkarte">Nur einmal</x-bento-card>');
    expect(substr_count($html, 'Nur einmal'))->toBe(1);
});
