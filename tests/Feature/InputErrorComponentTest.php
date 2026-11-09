<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('component renders without error bag', function (): void {
    $html = Blade::render('<x-input-error for="vorname" />');
    expect($html)->toContain('data-error-for="vorname"')
        ->toContain('role="status"');
});
