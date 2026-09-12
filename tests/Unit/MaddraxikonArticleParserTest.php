<?php

namespace Tests\Unit;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonArticleParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonArticleParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_main_series_without_rating(): void
    {
        $book = app(MaddraxikonArticleParser::class)->parse(
            $this->articleHtml('<b>695</b>', '<th>Trans-Meeraka-Express</th>'),
            'https://de.maddraxikon.com/wiki/Trans-Meeraka-Express',
            BookType::MaddraxDieDunkleZukunftDerErde,
        );

        $this->assertSame(695, $book->number);
        $this->assertSame('Trans-Meeraka-Express', $book->title);
        $this->assertSame('Weltrat', $book->cycle);
        $this->assertNull($book->rating);
        $this->assertSame(0, $book->votes);
        $this->assertSame(['Autor A', 'Autor B'], $book->authors);
        $this->assertSame('Trans-Meeraka-Express', $book->pageTitle);
    }

    #[DataProvider('specialNumberProvider')]
    public function test_parses_series_specific_numbers(
        BookType $type,
        string $navigation,
        string $titleCell,
    ): void {
        $book = app(MaddraxikonArticleParser::class)->parse(
            $this->articleHtml($navigation, $titleCell),
            'https://de.maddraxikon.com/wiki/Testroman',
            $type,
        );

        $this->assertSame(26, $book->number);
        $this->assertSame('Testroman', $book->title);
    }

    /** @return iterable<string, array{BookType, string, string}> */
    public static function specialNumberProvider(): iterable
    {
        $navigation = '<div class="heftartikel-navigationsleiste-anfang"><table><tr><td align="center"><i>026</i></td></tr></table></div>';

        yield 'Hardcover' => [BookType::MaddraxHardcover, $navigation, '<th><b>Testroman</b></th>'];
        yield 'Die Abenteurer' => [BookType::DieAbenteurer, $navigation, '<th>Testroman</th>'];
    }

    public function test_rejects_article_without_number_or_title(): void
    {
        $this->expectException(MaddraxikonCrawlException::class);

        app(MaddraxikonArticleParser::class)->parse(
            '<html><body>unvollständig</body></html>',
            'https://de.maddraxikon.com/wiki/Kaputt',
            BookType::MaddraxDieDunkleZukunftDerErde,
        );
    }

    public function test_page_title_uses_the_configured_maddraxikon_origin(): void
    {
        config(['maddraxikon.base_url' => 'https://wiki.example.test:8443']);

        $book = app(MaddraxikonArticleParser::class)->parse(
            $this->articleHtml('<b>1</b>', '<th>Alternativer Ursprung</th>'),
            'https://wiki.example.test:8443/wiki/MX_1',
            BookType::MaddraxDieDunkleZukunftDerErde,
        );

        $this->assertSame('MX 1', $book->pageTitle);
    }

    private function articleHtml(string $navigation, string $titleCell): string
    {
        return '<html><body>'.$navigation.'<table>
            <tr><td>Erstmals&nbsp;erschienen:</td><td>2026-09-01</td></tr>
            <tr><td>Zyklus:</td><td>Weltrat (50 Romane)</td></tr>
            <tr><td>Serie:</td><td>Hardcover</td></tr>
            <tr><td>Titel:</td>'.$titleCell.'</tr>
            <tr><td>Text:</td><td>Autor A, Autor B</td></tr>
        </table></body></html>';
    }
}
