<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewsletterArchivSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_ausgaben_table_has_indexes_for_archive_listings(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dieser Test prueft SQLite-Indexmetadaten.');
        }

        $indexNames = collect(DB::select("PRAGMA index_list('newsletter_ausgaben')"))
            ->pluck('name')
            ->all();

        $this->assertContains('newsletter_ausgaben_status_published_at_sent_at_index', $indexNames);
        $this->assertContains('newsletter_ausgaben_sent_at_created_at_index', $indexNames);

        $memberListingColumns = collect(DB::select("PRAGMA index_info('newsletter_ausgaben_status_published_at_sent_at_index')"))
            ->pluck('name')
            ->all();
        $adminListingColumns = collect(DB::select("PRAGMA index_info('newsletter_ausgaben_sent_at_created_at_index')"))
            ->pluck('name')
            ->all();

        $this->assertSame(['status', 'published_at', 'sent_at'], $memberListingColumns);
        $this->assertSame(['sent_at', 'created_at'], $adminListingColumns);
    }
}
