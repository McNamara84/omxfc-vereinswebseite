<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('auktionen table has indexes for listing queries', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Dieser Test prueft SQLite-Indexmetadaten.');
    }
    $indexNames = collect(DB::select("PRAGMA index_list('auktionen')"))->pluck('name')->all();
    expect($indexNames)->toContain('auktionen_status_created_at_index')
        ->toContain('auktionen_status_verkauft_at_index');
    $statusCreatedColumns = collect(DB::select("PRAGMA index_info('auktionen_status_created_at_index')"))->pluck('name')->all();
    $statusVerkauftColumns = collect(DB::select("PRAGMA index_info('auktionen_status_verkauft_at_index')"))->pluck('name')->all();
    expect($statusCreatedColumns)->toBe(['status', 'created_at'])
        ->and($statusVerkauftColumns)->toBe(['status', 'verkauft_at']);
});
