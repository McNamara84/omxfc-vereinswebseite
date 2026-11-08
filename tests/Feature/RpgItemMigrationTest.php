<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RpgItemMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['rpg_item_versions', 'rpg_ammunition_profiles', 'rpg_item_types'];

    #[DataProvider('emptySchemas')]
    public function test_rollback_accepts_missing_or_empty_tables(array $presentTables): void
    {
        $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');
        $migration->down();
        $this->createPartialSchema($presentTables);

        $migration->down();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public static function emptySchemas(): iterable
    {
        for ($mask = 0; $mask < 8; $mask++) {
            yield 'table combination '.$mask => [array_values(array_filter(self::TABLES, static fn ($table, $index) => ($mask & (1 << $index)) !== 0, ARRAY_FILTER_USE_BOTH))];
        }
    }

    #[DataProvider('populatedSchemas')]
    public function test_rollback_preserves_all_tables_when_any_existing_table_has_data(array $presentTables, string $populatedTable): void
    {
        $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');
        $migration->down();
        $this->createPartialSchema($presentTables);
        DB::table($populatedTable)->insert(['id' => 1]);

        try {
            $migration->down();
            $this->fail('A rollback must preserve saved RPG equipment data.');
        } catch (RuntimeException $error) {
            $this->assertSame('Gespeicherte RPG-Gegenstandsversionen müssen erhalten bleiben.', $error->getMessage());
            foreach (self::TABLES as $table) {
                $this->assertSame(in_array($table, $presentTables, true), Schema::hasTable($table));
            }
            $this->assertDatabaseHas($populatedTable, ['id' => 1]);
        }
    }

    public static function populatedSchemas(): iterable
    {
        foreach (self::TABLES as $table) {
            yield $table.' in complete schema' => [self::TABLES, $table];
            yield $table.' in partial schema' => [[$table], $table];
        }
    }

    public function test_an_empty_schema_can_be_recreated_after_rollback(): void
    {
        $migration = require database_path('migrations/2026_11_08_120000_create_rpg_item_types.php');

        $migration->down();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('rpg_item_types', 'current_version'));
        $this->assertTrue(Schema::hasColumn('rpg_item_versions', 'definition'));
        $this->assertTrue(Schema::hasColumn('rpg_ammunition_profiles', 'reference'));
    }

    private function createPartialSchema(array $tables): void
    {
        foreach ($tables as $table) {
            Schema::create($table, static function (Blueprint $blueprint): void {
                $blueprint->id();
            });
        }
    }
}
