<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaddraxikonPageTitle;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('MaddraxikonPageTitleTest.validUrls', static function (): iterable {
    yield 'index.php query' => ['https://de.maddraxikon.com/index.php?title=Die_dunkle_Seite', 'Die dunkle Seite'];
    yield 'short wiki path' => ['https://de.maddraxikon.com/wiki/MX_001_%E2%80%93_Der_Gott_aus_dem_Eis', 'MX 001 – Der Gott aus dem Eis'];
    yield 'fragment is ignored' => ['https://de.maddraxikon.com/wiki/MX_1#Handlung', 'MX 1'];
});
dataset('MaddraxikonPageTitleTest.invalidUrls', static function (): iterable {
    yield 'wrong scheme' => ['http://de.maddraxikon.com/wiki/MX_1'];
    yield 'wrong host' => ['https://example.com/wiki/MX_1'];
    yield 'lookalike host' => ['https://de.maddraxikon.com.example.org/wiki/MX_1'];
    yield 'wrong port' => ['https://de.maddraxikon.com:8443/wiki/MX_1'];
    yield 'empty path title' => ['https://de.maddraxikon.com/wiki/'];
    yield 'unrelated route' => ['https://de.maddraxikon.com/api.php?action=query'];
    yield 'control character' => ['https://de.maddraxikon.com/index.php?title=MX%0A1'];
    yield 'leading form feed' => ['https://de.maddraxikon.com/index.php?title=%0CMX_1'];
    yield 'trailing form feed' => ['https://de.maddraxikon.com/wiki/MX_1%0C'];
    yield 'encoded NUL' => ['https://de.maddraxikon.com/index.php?title=MX%001'];
    yield 'raw NUL query' => ["https://de.maddraxikon.com/index.php?title=MX\x00_1"];
});
test('it extracts canonical titles from supported maddraxikon urls', function (string $url, string $expected): void {
    expect(MaddraxikonPageTitle::fromUrl($url))->toBe($expected);
})->with('MaddraxikonPageTitleTest.validUrls');
test('it rejects untrusted or ambiguous urls', function (string $url): void {
    expect(MaddraxikonPageTitle::fromUrl($url))->toBeNull();
})->with('MaddraxikonPageTitleTest.invalidUrls');
test('database and display titles are normalized without fuzzy matching', function (): void {
    expect(MaddraxikonPageTitle::databaseKey('  MX  1 – Test  '))->toBe('MX_1_–_Test')
        ->and(MaddraxikonPageTitle::displayTitle('MX_1_–_Test'))->toBe('MX 1 – Test')
        ->and(MaddraxikonPageTitle::databaseKey("MX\n1"))->toBeNull()
        ->and(MaddraxikonPageTitle::databaseKey(''))->toBeNull()
        ->and(MaddraxikonPageTitle::databaseKey(str_repeat('A', 256)))->toBeNull();
});
test('it accepts only the configured origin and effective port', function (): void {
    $baseUrl = 'https://wiki.example.test:8443';
    expect(MaddraxikonPageTitle::fromUrl('https://wiki.example.test:8443/wiki/MX_1', $baseUrl))->toBe('MX 1')
        ->and(MaddraxikonPageTitle::fromUrl('https://wiki.example.test/wiki/MX_1', $baseUrl))->toBeNull()
        ->and(MaddraxikonPageTitle::fromUrl('https://de.maddraxikon.com/wiki/MX_1', $baseUrl))->toBeNull();
});
