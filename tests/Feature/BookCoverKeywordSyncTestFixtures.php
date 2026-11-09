<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BookCover;
use App\Services\CoverRatings\BookCoverKeywordSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBildersucheCover;

trait BookCoverKeywordSyncTestFixtures
{
    use CreatesBildersucheCover;
    use RefreshDatabase;

    private function sync(BookCover $cover, ?array $keywords, bool $dryRun = false): string
    {
        return app(BookCoverKeywordSynchronizer::class)->sync($cover, $cover->source_file_title, ['keywords' => $keywords, 'error' => $keywords === null ? 'Timeout' : null], $dryRun);
    }
}
