<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RomantauschBaxxSpecialOffersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_up_is_idempotent_when_table_already_exists(): void
    {
        $this->assertTrue(Schema::hasTable('romantausch_baxx_special_offers'));
        $this->assertTrue(Schema::hasColumns('romantausch_baxx_special_offers', [
            'action_key',
            'points',
            'every_count',
            'ends_at',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $migration = require database_path('migrations/2026_05_07_120000_create_romantausch_baxx_special_offers_table.php');

        $migration->up();

        $this->assertTrue(Schema::hasTable('romantausch_baxx_special_offers'));
        $this->assertTrue(Schema::hasColumns('romantausch_baxx_special_offers', [
            'action_key',
            'points',
            'every_count',
            'ends_at',
            'is_active',
            'created_at',
            'updated_at',
        ]));
    }
}