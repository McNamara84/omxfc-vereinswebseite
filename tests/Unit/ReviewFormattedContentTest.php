<?php

namespace Tests\Unit;

use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Review::class)]
class ReviewFormattedContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_paragraph_spacing(): void
    {
        $review = new Review(['content' => "Erster Absatz\n\nZweiter Absatz"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<p>Erster Absatz</p>', $formatted);
        $this->assertStringContainsString('<p>Zweiter Absatz</p>', $formatted);
    }

    public function test_renders_headings_lists_blockquotes_and_inline_code(): void
    {
        $review = new Review(['content' => "## Überschrift\n\n- Punkt eins\n- Punkt zwei\n\n> Zitat\n\n`Code`"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<h2>Überschrift</h2>', $formatted);
        $this->assertStringContainsString('<ul>', $formatted);
        $this->assertStringContainsString('<li>Punkt eins</li>', $formatted);
        $this->assertStringContainsString('<blockquote>', $formatted);
        $this->assertStringContainsString('<p>Zitat</p>', $formatted);
        $this->assertStringContainsString('<code>Code</code>', $formatted);
    }

    public function test_strips_disallowed_html_and_sanitizes_links(): void
    {
        $review = new Review(['content' => "Click [here](https://example.com)\n\n<script>alert('xss')</script>"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a href="https://example.com" rel="noopener noreferrer">here</a>', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('script', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('alert', $formatted);
    }

    public function test_returns_empty_for_blank_content(): void
    {
        $emptyReview = new Review(['content' => '']);
        $whitespaceReview = new Review(['content' => "   \n   "]);

        $this->assertSame('', $emptyReview->formatted_content);
        $this->assertSame('', $whitespaceReview->formatted_content);
    }

    public function test_renders_malformed_markdown_gracefully(): void
    {
        $review = new Review(['content' => "**Fetter Text ohne Ende\n*Unvollständige Aufzählung"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('Fetter Text', $formatted);
        $this->assertStringContainsString('Unvollständige Aufzählung', $formatted);
    }

    public function test_sanitizes_additional_xss_vectors_case_insensitive(): void
    {
        $review = new Review(['content' => "Unsafe [link](JaVaScRiPt:alert('XSS')) and [safe](HTTP://example.com) raw <a href=\"javascript:alert('xss')\" OnClick=\"alert('xss')\">Click</a> and data [uri](data:text/html,alert('boom'))"]);

        $formatted = $review->formatted_content;

        $this->assertStringNotContainsStringIgnoringCase('data:text/html', $formatted);
        $this->assertSame(0, preg_match('/<a[^>]*onclick/i', $formatted));
        $this->assertSame(0, preg_match('/<a[^>]*href="javascript/i', $formatted));
        $this->assertStringContainsStringIgnoringCase('href="http://example.com"', $formatted);
        $this->assertStringContainsString('rel="noopener noreferrer">safe</a>', $formatted);
        $this->assertStringContainsString('<a rel="noopener noreferrer">link</a>', $formatted);
    }

    public function test_renders_deeply_nested_structures_and_long_content(): void
    {
        $nestedMarkdown = "> Quote\n> \n> 1. Eins\n>    - Unterpunkt\n>      - Noch tiefer\n\n";
        $longContent = str_repeat('Langer Inhalt ', 500);
        $review = new Review(['content' => $nestedMarkdown . $longContent]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<blockquote>', $formatted);
        $this->assertStringContainsString('<ol>', $formatted);
        $this->assertStringContainsString('Langer Inhalt', $formatted);
        $this->assertSame(500, substr_count($formatted, 'Langer Inhalt'));
        $this->assertStringContainsString('<li>Eins', $formatted);
        $this->assertStringContainsString('<li>Unterpunkt', $formatted);
    }

    public function test_allows_relative_and_mailto_links_and_removes_styles(): void
    {
        $markdown = "[Relative](page.html) and [Docs](./docs) and [Mail](mailto:team@example.com) and <a href=\"https://example.com\" style=\"color:red\">Styled</a>";
        $review = new Review(['content' => $markdown]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a href="page.html" rel="noopener noreferrer">Relative</a>', $formatted);
        $this->assertStringContainsString('<a href="./docs" rel="noopener noreferrer">Docs</a>', $formatted);
        $this->assertStringContainsString('<a href="mailto:team@example.com" rel="noopener noreferrer">Mail</a>', $formatted);
        $this->assertStringNotContainsString('style=', $formatted);
    }

    public function test_handles_relative_link_shapes_with_queries_and_fragments(): void
    {
        $markdown = "[Nested](docs/v1/guide/page.html?ref=123#section) and [Letters](docs123/file-name_v2.md) and [Underscored](_drafts/notes.txt)";
        $review = new Review(['content' => $markdown]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a href="docs/v1/guide/page.html?ref=123#section" rel="noopener noreferrer">Nested</a>', $formatted);
        $this->assertStringContainsString('<a href="docs123/file-name_v2.md" rel="noopener noreferrer">Letters</a>', $formatted);
        $this->assertStringContainsString('<a href="_drafts/notes.txt" rel="noopener noreferrer">Underscored</a>', $formatted);
    }

    public function test_removes_mixed_case_protocols_in_raw_html(): void
    {
        $review = new Review(['content' => '<a href="JaVaScRiPt:alert(1)">Bad</a> and <a href="VBScript:msgbox(1)">Also bad</a>']);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('Bad', $formatted);
        $this->assertStringContainsString('Also bad', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('vbscript:', $formatted);
    }

    public function test_handles_malformed_urls_and_protocol_relative_links(): void
    {
        $review = new Review(['content' => 'Broken [broken](http:///example.com) and [protocol relative](//example.com/path) and [anchor](#anchor)']);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a rel="noopener noreferrer">broken</a>', $formatted);
        $this->assertStringContainsString('<a rel="noopener noreferrer">protocol relative</a>', $formatted);
        $this->assertStringContainsString('<a href="#anchor" rel="noopener noreferrer">anchor</a>', $formatted);
    }

    public function test_invalidates_cached_content_after_source_change(): void
    {
        $review = Review::factory()->create(['content' => 'Erster']);

        $first = $review->formatted_content;

        $review->update(['content' => 'Zweiter']);
        $second = $review->fresh()->formatted_content;

        $this->assertNotSame($first, $second);
        $this->assertStringContainsString('Zweiter', $second);
    }

    public function test_applies_defensive_relative_link_heuristics(): void
    {
        $markdown = "[Query](docs/page?section=1) and [Numeric](123start/page) and [Special](@notes/file) and [Subdir](docs/more/paths/file.txt)";
        $review = new Review(['content' => $markdown]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a href="docs/page?section=1" rel="noopener noreferrer">Query</a>', $formatted);
        $this->assertStringContainsString('<a rel="noopener noreferrer">Numeric</a>', $formatted);
        $this->assertStringContainsString('<a rel="noopener noreferrer">Special</a>', $formatted);
        $this->assertStringContainsString('<a href="docs/more/paths/file.txt" rel="noopener noreferrer">Subdir</a>', $formatted);
    }

    public function test_caches_formatted_content_across_instances(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $review = Review::factory()->create(['content' => 'Cached Inhalt']);

        $first = $review->formatted_content;

        $cacheKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Cached Inhalt'));
        $this->assertTrue(Cache::has($cacheKey));

        $reloaded = Review::find($review->id);
        $second = $reloaded->formatted_content;

        $this->assertSame($first, $second);
    }

    public function test_cached_content_persists_beyond_one_day(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $now = Carbon::now();
        Carbon::setTestNow($now);

        $review = Review::factory()->create(['content' => 'Zeitlos']);

        $first = $review->formatted_content;

        $cacheKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zeitlos'));

        $this->assertTrue(Cache::has($cacheKey));

        Carbon::setTestNow($now->copy()->addDays(2));

        $this->assertTrue(Cache::has($cacheKey));

        $second = Review::find($review->id)->formatted_content;

        $this->assertSame($first, $second);

        Carbon::setTestNow();
    }

    public function test_builds_cache_keys_from_persisted_timestamps(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $firstNow = Carbon::now();
        Carbon::setTestNow($firstNow);

        $review = Review::factory()->create(['content' => 'Zeitstempel Test']);

        $this->assertInstanceOf(Carbon::class, $review->updated_at);

        $initialKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zeitstempel Test'));

        $review->formatted_content;

        $this->assertTrue(Cache::has($initialKey));

        Carbon::setTestNow($firstNow->copy()->addSecond());

        $review->update(['content' => 'Zweite Version']);
        $review->refresh();

        $updatedKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zweite Version'));

        $this->assertNotSame($initialKey, $updatedKey);
        $review->formatted_content;
        $this->assertTrue(Cache::has($updatedKey));

        Carbon::setTestNow();
    }
}
