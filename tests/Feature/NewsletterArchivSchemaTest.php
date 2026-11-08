<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('newsletter ausgaben table has indexes for archive listings', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Dieser Test prueft SQLite-Indexmetadaten.');
    }
    $indexNames = collect(DB::select("PRAGMA index_list('newsletter_ausgaben')"))->pluck('name')->all();
    expect($indexNames)->toContain('newsletter_ausgaben_status_published_at_sent_at_index')
        ->toContain('newsletter_ausgaben_sent_at_created_at_index');
    $memberListingColumns = collect(DB::select("PRAGMA index_info('newsletter_ausgaben_status_published_at_sent_at_index')"))->pluck('name')->all();
    $adminListingColumns = collect(DB::select("PRAGMA index_info('newsletter_ausgaben_sent_at_created_at_index')"))->pluck('name')->all();
    expect($memberListingColumns)->toBe(['status', 'published_at', 'sent_at'])
        ->and($adminListingColumns)->toBe(['sent_at', 'created_at']);
});
