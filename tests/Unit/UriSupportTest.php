<?php

declare(strict_types=1);

use App\Support\UriSupport;

mutates(UriSupport::class);
covers(UriSupport::class);

test('normalizeAbsoluteHttpUrl accepts only absolute HTTP URLs with a host', function () {
    $normalizedUrl = expect(UriSupport::normalizeAbsoluteHttpUrl('https://maddrax-fanclub.de'))
        ->toBeString()
        ->toBeUrl()
        ->toBe('https://maddrax-fanclub.de')
        ->value;

    expect(parse_url($normalizedUrl, PHP_URL_HOST))->toBeHostname()->toBeDomain()->toBe('maddrax-fanclub.de')
        ->and(UriSupport::normalizeAbsoluteHttpUrl('http://example.com/path?x=1'))->toBe('http://example.com/path?x=1')
        ->and(UriSupport::normalizeAbsoluteHttpUrl('http:///example.com'))->toBeNull()
        ->and(UriSupport::normalizeAbsoluteHttpUrl('//example.com/path'))->toBeNull()
        ->and(UriSupport::normalizeAbsoluteHttpUrl('docs/page'))->toBeNull();
});

test('resolve builds absolute URLs from relative references', function () {
    expect(UriSupport::resolve('https://de.maddraxikon.com/', 'wiki/A1'))->toBe('https://de.maddraxikon.com/wiki/A1')
        ->and(UriSupport::resolve('https://de.maddraxikon.com/', 'index.php?title=Kategorie:2012-Heftromane&pagefrom=2'))->toBe('https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane&pagefrom=2');
});

test('absolute host matching is case-insensitive but rejects ambiguous hosts', function () {
    expect(UriSupport::isAbsoluteUrlForHost(
        'HTTPS://MADDRAX-FANCLUB.DE/path',
        'https',
        'maddrax-fanclub.de'
    ))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('//maddrax-fanclub.de/path', 'https', 'maddrax-fanclub.de'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://example.com', 'https', 'maddrax-fanclub.de'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://maddrax-fanclub.de.evil.example', 'https', 'maddrax-fanclub.de'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://user@maddrax-fanclub.de', 'http', 'maddrax-fanclub.de'))->toBeFalse();
});

test('safe Markdown href matches the existing link policy', function () {
    expect(UriSupport::isSafeMarkdownHref('https://example.com'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('http://example.com/path'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('mailto:team@example.com'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('#anchor'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('./docs'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('docs/page?section=1'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('javascript:alert(1)'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('data:text/html,boom'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('vbscript:msgbox(1)'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('//example.com/path'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('http:///example.com'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('123start/page'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('@notes/file'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('https://'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('mailto:'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref("javascript:\0alert(1)"))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('\\\\example.com\\share'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('../docs/readme.md#intro'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('/absolute/path?section=1'))->toBeTrue();
});
