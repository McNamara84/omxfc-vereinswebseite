<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

// Functional test files explicitly select their base class and database traits.
// This also preserves isolated integration tests that do not boot Laravel.

pest()->extend(TestCase::class)->use(RefreshDatabase::class, CreatesUserWithRole::class)->in('Browser');

pest()->browser()->timeout(30000);

// TIA stays opt-in locally and consumes the shared main-branch baseline.
// Its dedicated PHPUnit configuration contains only functional Pest tests.
pest()->tia()
    ->baselined()
    ->filtered()
    ->watch([
        'config/**' => 'tests',
        'routes/**' => 'tests',
        'bootstrap/**' => 'tests',
        'phpunit*.xml' => 'tests',
        'tests/TestCase.php' => 'tests',
        'tests/bootstrap.php' => 'tests',
        'tests/Support/**' => 'tests',
        'tests/Concerns/**' => 'tests',
        'tests/Datasets/**' => 'tests',
        'tests/**/*Fixtures.php' => 'tests',
        'database/seeders/**' => 'tests',
        'tools/testing/**' => 'tests',
        'docker/tests/**' => 'tests',
        'docker-compose.tests.yml' => 'tests',
        '.env.testing' => 'tests',
    ]);
