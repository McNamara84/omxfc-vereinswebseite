<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('theme bootstrap partial includes shared script', function (): void {
    $rendered = Blade::render("@include('layouts.partials.theme-bootstrap')");
    $inlineScript = trim(file_get_contents(resource_path('js/theme/bootstrap-inline.js')));
    expect($rendered)->toContain('<script>')
        ->toContain('</script>')
        ->toContain($inlineScript);
});

PhpunitMetadata::onFile(__FILE__, CoversNothing::class);
