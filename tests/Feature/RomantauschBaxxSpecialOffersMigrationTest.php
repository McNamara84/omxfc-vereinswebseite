<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('migration up is idempotent when table already exists', function (): void {
    expect(Schema::hasTable('romantausch_baxx_special_offers'))->toBeTrue()
        ->and(Schema::hasColumns('romantausch_baxx_special_offers', ['action_key', 'points', 'every_count', 'ends_at', 'is_active', 'created_at', 'updated_at']))->toBeTrue();
    $migration = require database_path('migrations/2026_05_07_120000_create_romantausch_baxx_special_offers_table.php');
    $migration->up();
    expect(Schema::hasTable('romantausch_baxx_special_offers'))->toBeTrue()
        ->and(Schema::hasColumns('romantausch_baxx_special_offers', ['action_key', 'points', 'every_count', 'ends_at', 'is_active', 'created_at', 'updated_at']))->toBeTrue();
});
