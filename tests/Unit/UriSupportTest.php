<?php

namespace Tests\Unit;

use App\Support\UriSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriSupport::class)]
class UriSupportTest extends TestCase
{
    #[Test]
    public function normalize_absolute_http_url_accepts_only_absolute_http_urls_with_host(): void
    {
        $this->assertSame('https://maddrax-fanclub.de', UriSupport::normalizeAbsoluteHttpUrl('https://maddrax-fanclub.de'));
        $this->assertSame('http://example.com/path?x=1', UriSupport::normalizeAbsoluteHttpUrl('http://example.com/path?x=1'));
        $this->assertNull(UriSupport::normalizeAbsoluteHttpUrl('http:///example.com'));
        $this->assertNull(UriSupport::normalizeAbsoluteHttpUrl('//example.com/path'));
        $this->assertNull(UriSupport::normalizeAbsoluteHttpUrl('docs/page'));
    }

    #[Test]
    public function resolve_builds_absolute_urls_from_relative_references(): void
    {
        $this->assertSame(
            'https://de.maddraxikon.com/wiki/A1',
            UriSupport::resolve('https://de.maddraxikon.com/', 'wiki/A1')
        );

        $this->assertSame(
            'https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane&pagefrom=2',
            UriSupport::resolve('https://de.maddraxikon.com/', 'index.php?title=Kategorie:2012-Heftromane&pagefrom=2')
        );
    }

    #[Test]
    public function safe_markdown_href_matches_existing_link_policy(): void
    {
        $this->assertTrue(UriSupport::isSafeMarkdownHref('https://example.com'));
        $this->assertTrue(UriSupport::isSafeMarkdownHref('http://example.com/path'));
        $this->assertTrue(UriSupport::isSafeMarkdownHref('mailto:team@example.com'));
        $this->assertTrue(UriSupport::isSafeMarkdownHref('#anchor'));
        $this->assertTrue(UriSupport::isSafeMarkdownHref('./docs'));
        $this->assertTrue(UriSupport::isSafeMarkdownHref('docs/page?section=1'));

        $this->assertFalse(UriSupport::isSafeMarkdownHref('javascript:alert(1)'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('data:text/html,boom'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('vbscript:msgbox(1)'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('//example.com/path'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('http:///example.com'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('123start/page'));
        $this->assertFalse(UriSupport::isSafeMarkdownHref('@notes/file'));
    }
}
