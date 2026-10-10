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

test('HTTP normalization rejects unsafe input and unsupported schemes', function (string $url) {
    expect(UriSupport::normalizeAbsoluteHttpUrl($url))->toBeNull();
})->with(['', 'ftp://example.com/file', 'mailto:a@example.com', 'https://', 'http://[broken', "https://example.com/\npath", 'https://example.com/a b', 'https:\\example.com']);

test('host matching uses the requested protocol and port including HTTP defaults', function () {
    expect(UriSupport::isAbsoluteUrlForHost('http://example.com', 'HTTP', 'EXAMPLE.COM'))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('http://example.com:80/path', 'http', 'example.com'))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('http://example.com', 'https', 'example.com'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://example.com', 'https', 'example.com', 8443))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('http://example.com:81', 'http', 'example.com'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('ftp://example.com', 'ftp', 'example.com'))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('ftp://example.com:21', 'ftp', 'example.com'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('ftp://example.com:21', 'ftp', 'example.com', 21))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('docs/page', 'https', 'example.com'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('http://[broken', 'https', 'example.com'))->toBeFalse();
});

test('host matching cannot accept a scheme-relative URL with an empty expected scheme', function () {
    expect(UriSupport::isAbsoluteUrlForHost('//example.com/path', '', 'example.com'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('http:///path', 'http', ''))->toBeFalse()
        ->and(UriSupport::normalizeAbsoluteHttpUrl('https:relative'))->toBeNull()
        ->and(UriSupport::isSafeMarkdownHref('https:relative'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('///example.com/path'))->toBeFalse();
});

test('native URI validation rejects literal control characters, spaces and backslashes', function () {
    foreach ([...range(0, 32), 127, 92] as $byte) {
        $url = 'https://example.com/a'.chr($byte).'file';
        expect(UriSupport::normalizeAbsoluteHttpUrl($url))->toBeNull()
            ->and(UriSupport::isAbsoluteUrlForHost($url, 'https', 'example.com'))->toBeFalse()
            ->and(UriSupport::isSafeMarkdownHref($url))->toBeFalse();
    }

    expect(UriSupport::normalizeAbsoluteHttpUrl('HTTPS://EXAMPLE.COM/path'))->toBe('https://example.com/path')
        ->and(UriSupport::isSafeMarkdownHref('MAILTO:team@example.com'))->toBeTrue()
        ->and(UriSupport::resolve('ftp://example.com/', 'page'))->toBeNull();
});

test('resolution rejects invalid bases and malformed references', function () {
    expect(UriSupport::resolve('/relative', 'page'))->toBeNull()
        ->and(UriSupport::resolve('https://example.com/', 'http://[broken'))->toBeNull()
        ->and(UriSupport::resolve('https://example.com/path', '#section'))->toBe('https://example.com/path#section')
        ->and(UriSupport::resolve('http://example.com/docs/', 'page'))->toBe('http://example.com/docs/page')
        ->and(UriSupport::resolve('https:relative', 'page'))->toBeNull();
});

test('resolve builds absolute URLs from relative references', function () {
    expect(UriSupport::resolve('https://de.maddraxikon.com/', 'wiki/A1'))->toBe('https://de.maddraxikon.com/wiki/A1')
        ->and(UriSupport::resolve('https://de.maddraxikon.com/', 'index.php?title=Kategorie:2012-Heftromane&pagefrom=2'))->toBe('https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane&pagefrom=2')
        ->and(UriSupport::resolve('https://de.maddraxikon.com/wiki/serie/band', '../figuren/aruula'))->toBe('https://de.maddraxikon.com/wiki/figuren/aruula');
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
        ->and(UriSupport::isAbsoluteUrlForHost('https://maddrax-fanclub.de:443/path', 'https', 'maddrax-fanclub.de'))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('https://maddrax-fanclub.de:8443/path', 'https', 'maddrax-fanclub.de'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://maddrax-fanclub.de:8443/path', 'https', 'maddrax-fanclub.de', 8443))->toBeTrue()
        ->and(UriSupport::isAbsoluteUrlForHost('https://user@maddrax-fanclub.de', 'https', 'maddrax-fanclub.de'))->toBeFalse()
        ->and(UriSupport::isAbsoluteUrlForHost('https://user:password@maddrax-fanclub.de/path', 'https', 'maddrax-fanclub.de'))->toBeFalse();
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
        ->and(UriSupport::isSafeMarkdownHref("https://example.com/\r\nmalicious"))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('https:\\example.com'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('\\\\example.com\\share'))->toBeFalse()
        ->and(UriSupport::isSafeMarkdownHref('../docs/readme.md#intro'))->toBeTrue()
        ->and(UriSupport::isSafeMarkdownHref('/absolute/path?section=1'))->toBeTrue();
});
