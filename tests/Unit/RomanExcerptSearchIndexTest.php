<?php

namespace Tests\Unit;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RomanExcerptSearchIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_lexical_and_hybrid_indexes_are_versioned_separately(): void
    {
        config([
            'scout.prefix' => 'test_',
            'kompendium.search.mode' => 'lexical',
            'kompendium.search.index_version' => 3,
        ]);

        $this->assertSame('test_roman_excerpts', (new RomanExcerpt)->searchableAs());

        config(['kompendium.search.mode' => 'hybrid']);

        $this->assertSame('test_roman_excerpts_hybrid_v3', (new RomanExcerpt)->searchableAs());
    }
}
