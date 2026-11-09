<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

trait RpgItemMigrationTestFixtures
{
    use RefreshDatabase;

    private const TABLES = ['rpg_item_versions', 'rpg_ammunition_profiles', 'rpg_item_types'];

    private function createPartialSchema(array $tables): void
    {
        foreach ($tables as $table) {
            Schema::create($table, static function (Blueprint $blueprint): void {
                $blueprint->id();
            });
        }
    }
}
