<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('query mode does not change the active index variant', function (): void {
    config(['scout.prefix' => 'test_', 'kompendium.search.mode' => 'lexical', 'kompendium.search.index_variant' => 'lexical', 'kompendium.search.index_version' => 3]);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts_lexical_v3');
    config(['kompendium.search.mode' => 'hybrid']);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts_lexical_v3');
    config(['kompendium.search.mode' => 'lexical', 'kompendium.search.index_variant' => 'hybrid']);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts_hybrid_v3');
    config(['kompendium.search.mode' => 'hybrid']);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts_hybrid_v3');
});
