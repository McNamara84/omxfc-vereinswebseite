<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonArticleParser;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonArticleParserTestFixtures::class);
dataset('MaddraxikonArticleParserTest.specialNumberProvider', static function (): iterable {
    $navigation = '<div class="heftartikel-navigationsleiste-anfang"><table><tr><td align="center"><i>026</i></td></tr></table></div>';
    yield 'Hardcover' => [BookType::MaddraxHardcover, $navigation, '<th><b>Testroman</b></th>'];
    yield 'Die Abenteurer' => [BookType::DieAbenteurer, $navigation, '<th>Testroman</th>'];
});
test('parses main series without rating', function (): void {
    $book = app(MaddraxikonArticleParser::class)->parse($this->articleHtml('<b>695</b>', '<th>Trans-Meeraka-Express</th>'), 'https://de.maddraxikon.com/wiki/Trans-Meeraka-Express', BookType::MaddraxDieDunkleZukunftDerErde);
    expect($book->number)->toBe(695)
        ->and($book->title)->toBe('Trans-Meeraka-Express')
        ->and($book->cycle)->toBe('Weltrat')
        ->and($book->rating)->toBeNull()
        ->and($book->votes)->toBe(0)
        ->and($book->authors)->toBe(['Autor A', 'Autor B'])
        ->and($book->pageTitle)->toBe('Trans-Meeraka-Express');
});

test('parses the blackout infobox and ignores links to missing following books', function (): void {
    $html = '<html><head><meta charset="UTF-8"></head><body>
        <div class="nav-zahlen"><a href="/index.php?title=Quelle:MX699">699</a>|<b>700</b>|<a class="new" href="/index.php?title=Quelle:MX701&amp;action=edit&amp;redlink=1">701</a></div>
        <table><tr><td>Zyklus:</td><td><a href="/index.php?title=Blackout_(Zyklus)">Blackout (Zyklus)</a></td></tr>
        <tr><td>Titel:</td><th><b>Blackout</b></th></tr>
        <tr><td>Text:</td><td>Oliver Müller</td></tr>
        <tr><td>Erstmals erschienen:</td><td>14.11.2026</td></tr></table></body></html>';
    $book = app(MaddraxikonArticleParser::class)->parse($html, 'https://de.maddraxikon.com/index.php?title=Blackout', BookType::MaddraxDieDunkleZukunftDerErde);

    expect($book->number)->toBe(700)
        ->and($book->title)->toBe('Blackout')
        ->and($book->cycle)->toBe('Blackout')
        ->and($book->authors)->toBe(['Oliver Müller'])
        ->and($book->releasedAt)->toBe('14.11.2026')
        ->and($book->pageTitle)->toBe('Blackout')
        ->and($book->rating)->toBeNull()
        ->and($book->votes)->toBe(0);
});
test('parses series specific numbers', function (BookType $type, string $navigation, string $titleCell): void {
    $book = app(MaddraxikonArticleParser::class)->parse($this->articleHtml($navigation, $titleCell), 'https://de.maddraxikon.com/wiki/Testroman', $type);
    expect($book->number)->toBe(26)
        ->and($book->title)->toBe('Testroman');
})->with('MaddraxikonArticleParserTest.specialNumberProvider');
test('2012 series uses navigation number instead of series year', function (): void {
    $seriesHeader = '<table><tr><td><b>2012</b></td></tr></table>';
    $navigation = '<div class="heftartikel-navigationsleiste-anfang"><table><tr><td align="center"><i><b>10</b></i></td></tr></table></div>';
    $book = app(MaddraxikonArticleParser::class)->parse($this->articleHtml($seriesHeader.$navigation, '<th>Im Bann der Loge</th>'), 'https://de.maddraxikon.com/wiki/Im_Bann_der_Loge', BookType::ZweiTausendZwölfDasJahrDerApokalypse);
    expect($book->number)->toBe(10)
        ->and($book->title)->toBe('Im Bann der Loge');
});
test('rejects article without number or title', function (): void {
    expect(fn () => app(MaddraxikonArticleParser::class)->parse('<html><body>unvollständig</body></html>', 'https://de.maddraxikon.com/wiki/Kaputt', BookType::MaddraxDieDunkleZukunftDerErde))->toThrow(MaddraxikonCrawlException::class);
});
test('page title uses the configured maddraxikon origin', function (): void {
    config(['maddraxikon.base_url' => 'https://wiki.example.test:8443']);
    $book = app(MaddraxikonArticleParser::class)->parse($this->articleHtml('<b>1</b>', '<th>Alternativer Ursprung</th>'), 'https://wiki.example.test:8443/wiki/MX_1', BookType::MaddraxDieDunkleZukunftDerErde);
    expect($book->pageTitle)->toBe('MX 1');
});
