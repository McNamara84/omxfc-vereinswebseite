<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(Review::class);

test('formats paragraph spacing', function (): void {
    $review = new Review(['content' => "Erster Absatz\n\nZweiter Absatz"]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<p>Erster Absatz</p>')
        ->toContain('<p>Zweiter Absatz</p>');
});

test('renders headings lists blockquotes and inline code', function (): void {
    $review = new Review(['content' => "## Überschrift\n\n- Punkt eins\n- Punkt zwei\n\n> Zitat\n\n`Code`"]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<h2>Überschrift</h2>')
        ->toContain('<ul>')
        ->toContain('<li>Punkt eins</li>')
        ->toContain('<blockquote>')
        ->toContain('<p>Zitat</p>')
        ->toContain('<code>Code</code>');
});

test('strips disallowed html and sanitizes links', function (): void {
    $review = new Review(['content' => "Click [here](https://example.com)\n\n<script>alert('xss')</script>"]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<a href="https://example.com" rel="noopener noreferrer">here</a>');
    $this->assertStringNotContainsStringIgnoringCase('script', $formatted);
    $this->assertStringNotContainsStringIgnoringCase('alert', $formatted);
});

test('returns empty for blank content', function (): void {
    $emptyReview = new Review(['content' => '']);
    $whitespaceReview = new Review(['content' => "   \n   "]);
    expect($emptyReview->formatted_content)->toBe('')
        ->and($whitespaceReview->formatted_content)->toBe('');
});

test('renders malformed markdown gracefully', function (): void {
    $review = new Review(['content' => "**Fetter Text ohne Ende\n*Unvollständige Aufzählung"]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('Fetter Text')
        ->toContain('Unvollständige Aufzählung');
});

test('sanitizes additional xss vectors case insensitive', function (): void {
    $review = new Review(['content' => "Unsafe [link](JaVaScRiPt:alert('XSS')) and [safe](HTTP://example.com) raw <a href=\"javascript:alert('xss')\" OnClick=\"alert('xss')\">Click</a> and data [uri](data:text/html,alert('boom'))"]);
    $formatted = $review->formatted_content;
    $this->assertStringNotContainsStringIgnoringCase('data:text/html', $formatted);
    expect(preg_match('/<a[^>]*onclick/i', $formatted))->toBe(0)
        ->and(preg_match('/<a[^>]*href="javascript/i', $formatted))->toBe(0);
    $this->assertStringContainsStringIgnoringCase('href="http://example.com"', $formatted);
    expect($formatted)->toContain('rel="noopener noreferrer">safe</a>')
        ->toContain('<a rel="noopener noreferrer">link</a>');
});

test('renders deeply nested structures and long content', function (): void {
    $nestedMarkdown = "> Quote\n> \n> 1. Eins\n>    - Unterpunkt\n>      - Noch tiefer\n\n";
    $longContent = str_repeat('Langer Inhalt ', 500);
    $review = new Review(['content' => $nestedMarkdown.$longContent]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<blockquote>')
        ->toContain('<ol>')
        ->toContain('Langer Inhalt')
        ->and(substr_count($formatted, 'Langer Inhalt'))->toBe(500)
        ->and($formatted)->toContain('<li>Eins')
        ->toContain('<li>Unterpunkt');
});

test('allows relative and mailto links and removes styles', function (): void {
    $markdown = '[Relative](page.html) and [Docs](./docs) and [Mail](mailto:team@example.com) and <a href="https://example.com" style="color:red">Styled</a>';
    $review = new Review(['content' => $markdown]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<a href="page.html" rel="noopener noreferrer">Relative</a>')
        ->toContain('<a href="./docs" rel="noopener noreferrer">Docs</a>')
        ->toContain('<a href="mailto:team@example.com" rel="noopener noreferrer">Mail</a>')->not->toContain('style=');
});

test('handles relative link shapes with queries and fragments', function (): void {
    $markdown = '[Nested](docs/v1/guide/page.html?ref=123#section) and [Letters](docs123/file-name_v2.md) and [Underscored](_drafts/notes.txt)';
    $review = new Review(['content' => $markdown]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<a href="docs/v1/guide/page.html?ref=123#section" rel="noopener noreferrer">Nested</a>')
        ->toContain('<a href="docs123/file-name_v2.md" rel="noopener noreferrer">Letters</a>')
        ->toContain('<a href="_drafts/notes.txt" rel="noopener noreferrer">Underscored</a>');
});

test('removes mixed case protocols in raw html', function (): void {
    $review = new Review(['content' => '<a href="JaVaScRiPt:alert(1)">Bad</a> and <a href="VBScript:msgbox(1)">Also bad</a>']);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('Bad')
        ->toContain('Also bad');
    $this->assertStringNotContainsStringIgnoringCase('javascript:', $formatted);
    $this->assertStringNotContainsStringIgnoringCase('vbscript:', $formatted);
});

test('handles malformed urls and protocol relative links', function (): void {
    $review = new Review(['content' => 'Broken [broken](http:///example.com) and [protocol relative](//example.com/path) and [anchor](#anchor)']);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<a rel="noopener noreferrer">broken</a>')
        ->toContain('<a rel="noopener noreferrer">protocol relative</a>')
        ->toContain('<a href="#anchor" rel="noopener noreferrer">anchor</a>');
});

test('invalidates cached content after source change', function (): void {
    $review = Review::factory()->create(['content' => 'Erster']);
    $first = $review->formatted_content;
    $review->update(['content' => 'Zweiter']);
    $second = $review->fresh()->formatted_content;
    expect($second)->not->toBe($first)
        ->toContain('Zweiter');
});

test('applies defensive relative link heuristics', function (): void {
    $markdown = '[Query](docs/page?section=1) and [Numeric](123start/page) and [Special](@notes/file) and [Subdir](docs/more/paths/file.txt)';
    $review = new Review(['content' => $markdown]);
    $formatted = $review->formatted_content;
    expect($formatted)->toContain('<a href="docs/page?section=1" rel="noopener noreferrer">Query</a>')
        ->toContain('<a rel="noopener noreferrer">Numeric</a>')
        ->toContain('<a rel="noopener noreferrer">Special</a>')
        ->toContain('<a href="docs/more/paths/file.txt" rel="noopener noreferrer">Subdir</a>');
});

test('caches formatted content across instances', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $review = Review::factory()->create(['content' => 'Cached Inhalt']);
    $first = $review->formatted_content;
    $cacheKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Cached Inhalt'));
    expect(Cache::has($cacheKey))->toBeTrue();
    $reloaded = Review::find($review->id);
    $second = $reloaded->formatted_content;
    expect($second)->toBe($first);
});

test('cached content persists beyond one day', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $now = Carbon::now();
    Carbon::setTestNow($now);
    $review = Review::factory()->create(['content' => 'Zeitlos']);
    $first = $review->formatted_content;
    $cacheKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zeitlos'));
    expect(Cache::has($cacheKey))->toBeTrue();
    Carbon::setTestNow($now->copy()->addDays(2));
    expect(Cache::has($cacheKey))->toBeTrue();
    $second = Review::find($review->id)->formatted_content;
    expect($second)->toBe($first);
    Carbon::setTestNow();
});

test('builds cache keys from persisted timestamps', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $firstNow = Carbon::now();
    Carbon::setTestNow($firstNow);
    $review = Review::factory()->create(['content' => 'Zeitstempel Test']);
    expect($review->updated_at)->toBeInstanceOf(Carbon::class);
    $initialKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zeitstempel Test'));
    $review->formatted_content;
    expect(Cache::has($initialKey))->toBeTrue();
    Carbon::setTestNow($firstNow->copy()->addSecond());
    $review->update(['content' => 'Zweite Version']);
    $review->refresh();
    $updatedKey = sprintf('review:%s:formatted:%s:%s', $review->id, $review->updated_at->format('Uu'), md5('Zweite Version'));
    expect($updatedKey)->not->toBe($initialKey);
    $review->formatted_content;
    expect(Cache::has($updatedKey))->toBeTrue();
    Carbon::setTestNow();
});
