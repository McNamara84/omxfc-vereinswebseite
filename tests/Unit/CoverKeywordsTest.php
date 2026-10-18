<?php

namespace Tests\Unit;

use App\Support\CoverRatings\CoverKeywords;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverKeywordsTest extends TestCase
{
    public function test_normalizes_unicode_whitespace_and_duplicates_without_losing_display_names(): void
    {
        $this->assertSame(['aruula' => 'Aruula', 'f-17 alpha 2' => 'F-17 Alpha 2', 'äther' => 'Äther'],
            CoverKeywords::values([' Aruula ', 'aruula', "F-17\nAlpha 2", 'Äther', '', '  ']));
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_lists(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoverKeywords::values($values);
    }

    public static function invalidValues(): array
    {
        return [[[null]], [[['bad']]], [[str_repeat('a', 256)]], [['key' => 'Aruula']], [["\xFF"]], [array_fill(0, 501, 'a')]];
    }

    #[DataProvider('fileTitles')]
    public function test_canonicalizes_namespace_and_first_letter_only(string $input, string $expected): void
    {
        $this->assertSame($expected, CoverKeywords::fileTitle($input));
    }

    public static function fileTitles(): array
    {
        return [['File:alpha_Beta.jpg', 'Datei:Alpha Beta.jpg'], ['Bild:001tibi.jpg', 'Datei:001tibi.jpg'], ['001tibi.jpg', 'Datei:001tibi.jpg'], ['Datei:Äther.jpg', 'Datei:Äther.jpg']];
    }

    #[DataProvider('invalidTitles')]
    public function test_rejects_unsafe_titles(string $title): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoverKeywords::fileTitle($title);
    }

    public static function invalidTitles(): array
    {
        return [[''], ['Datei:'], ['File:foo]] OR [[+'], ['File:foo|?Secret'], ['File:<script>'], [str_repeat('x', 256)]];
    }
}
