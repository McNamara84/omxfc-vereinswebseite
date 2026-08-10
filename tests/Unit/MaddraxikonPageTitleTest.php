<?php

namespace Tests\Unit;

use App\Support\MaddraxikonPageTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaddraxikonPageTitleTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_it_extracts_canonical_titles_from_supported_maddraxikon_urls(
        string $url,
        string $expected,
    ): void {
        $this->assertSame($expected, MaddraxikonPageTitle::fromUrl($url));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validUrls(): iterable
    {
        yield 'index.php query' => [
            'https://de.maddraxikon.com/index.php?title=Die_dunkle_Seite',
            'Die dunkle Seite',
        ];
        yield 'short wiki path' => [
            'https://de.maddraxikon.com/wiki/MX_001_%E2%80%93_Der_Gott_aus_dem_Eis',
            'MX 001 – Der Gott aus dem Eis',
        ];
        yield 'fragment is ignored' => [
            'https://de.maddraxikon.com/wiki/MX_1#Handlung',
            'MX 1',
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_untrusted_or_ambiguous_urls(string $url): void
    {
        $this->assertNull(MaddraxikonPageTitle::fromUrl($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'wrong scheme' => ['http://de.maddraxikon.com/wiki/MX_1'];
        yield 'wrong host' => ['https://example.com/wiki/MX_1'];
        yield 'lookalike host' => ['https://de.maddraxikon.com.example.org/wiki/MX_1'];
        yield 'empty path title' => ['https://de.maddraxikon.com/wiki/'];
        yield 'unrelated route' => ['https://de.maddraxikon.com/api.php?action=query'];
        yield 'control character' => ['https://de.maddraxikon.com/index.php?title=MX%0A1'];
    }

    public function test_database_and_display_titles_are_normalized_without_fuzzy_matching(): void
    {
        $this->assertSame(
            'MX_1_–_Test',
            MaddraxikonPageTitle::databaseKey('  MX  1 – Test  ')
        );
        $this->assertSame(
            'MX 1 – Test',
            MaddraxikonPageTitle::displayTitle('MX_1_–_Test')
        );
        $this->assertNull(MaddraxikonPageTitle::databaseKey("MX\n1"));
        $this->assertNull(MaddraxikonPageTitle::databaseKey(''));
        $this->assertNull(MaddraxikonPageTitle::databaseKey(str_repeat('A', 256)));
    }
}
