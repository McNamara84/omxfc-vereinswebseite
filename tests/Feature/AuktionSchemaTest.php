<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuktionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_auktionen_table_has_indexes_for_listing_queries(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dieser Test prueft SQLite-Indexmetadaten.');
        }

        $indexNames = collect(DB::select("PRAGMA index_list('auktionen')"))
            ->pluck('name')
            ->all();

        $this->assertContains('auktionen_status_created_at_index', $indexNames);
        $this->assertContains('auktionen_status_verkauft_at_index', $indexNames);

        $statusCreatedColumns = collect(DB::select("PRAGMA index_info('auktionen_status_created_at_index')"))
            ->pluck('name')
            ->all();
        $statusVerkauftColumns = collect(DB::select("PRAGMA index_info('auktionen_status_verkauft_at_index')"))
            ->pluck('name')
            ->all();

        $this->assertSame(['status', 'created_at'], $statusCreatedColumns);
        $this->assertSame(['status', 'verkauft_at'], $statusVerkauftColumns);
    }
}
