<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgItemMigrationTestFixtures::class);
dataset('RpgItemMigrationTest.emptySchemas', static function (): iterable {
    for ($mask = 0; $mask < 8; $mask++) {
        yield 'table combination '.$mask => [array_values(array_filter(['rpg_item_versions', 'rpg_ammunition_profiles', 'rpg_item_types'], static fn ($table, $index) => ($mask & 1 << $index) !== 0, ARRAY_FILTER_USE_BOTH))];
    }
});
dataset('RpgItemMigrationTest.populatedSchemas', static function (): iterable {
    foreach (['rpg_item_versions', 'rpg_ammunition_profiles', 'rpg_item_types'] as $table) {
        yield $table.' in complete schema' => [['rpg_item_versions', 'rpg_ammunition_profiles', 'rpg_item_types'], $table];
        yield $table.' in partial schema' => [[$table], $table];
    }
});
test('rollback accepts missing or empty tables', function (array $presentTables): void {
    $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');
    $migration->down();
    $this->createPartialSchema($presentTables);
    $migration->down();
    foreach (self::TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
})->with('RpgItemMigrationTest.emptySchemas');
test('rollback preserves all tables when any existing table has data', function (array $presentTables, string $populatedTable): void {
    $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');
    $migration->down();
    $this->createPartialSchema($presentTables);
    DB::table($populatedTable)->insert(['id' => 1]);
    try {
        $migration->down();
        $this->fail('A rollback must preserve saved RPG equipment data.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Gespeicherte RPG-Gegenstandsversionen müssen erhalten bleiben.');
        foreach (self::TABLES as $table) {
            expect(Schema::hasTable($table))->toBe(in_array($table, $presentTables, true));
        }
        $this->assertDatabaseHas($populatedTable, ['id' => 1]);
    }
})->with('RpgItemMigrationTest.populatedSchemas');
test('an empty schema can be recreated after rollback', function (): void {
    $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');
    $migration->down();
    $migration->up();
    expect(Schema::hasColumn('rpg_item_types', 'current_version'))->toBeTrue()
        ->and(Schema::hasColumn('rpg_item_versions', 'definition'))->toBeTrue()
        ->and(Schema::hasColumn('rpg_ammunition_profiles', 'reference'))->toBeTrue();
});
