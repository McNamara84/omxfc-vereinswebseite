<?php

namespace Tests\Unit;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RomanExcerptSearchIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_mode_does_not_change_the_active_index_variant(): void
    {
        config([
            'scout.prefix' => 'test_',
            'kompendium.search.mode' => 'lexical',
            'kompendium.search.index_variant' => 'lexical',
            'kompendium.search.index_version' => 3,
        ]);

        $this->assertSame('test_roman_excerpts', (new RomanExcerpt)->searchableAs());

        config(['kompendium.search.mode' => 'hybrid']);

        $this->assertSame('test_roman_excerpts', (new RomanExcerpt)->searchableAs());

        config([
            'kompendium.search.mode' => 'lexical',
            'kompendium.search.index_variant' => 'hybrid',
        ]);

        $this->assertSame('test_roman_excerpts_hybrid_v3', (new RomanExcerpt)->searchableAs());

        config(['kompendium.search.mode' => 'hybrid']);

        $this->assertSame('test_roman_excerpts_hybrid_v3', (new RomanExcerpt)->searchableAs());
    }
}
