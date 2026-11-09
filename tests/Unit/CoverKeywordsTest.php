<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CoverRatings\CoverKeywords;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('CoverKeywordsTest.invalidValues', static fn (): array => [
    'null keyword' => [[null]],
    'nested keyword list' => [[['bad']]],
    'overlong keyword' => [[str_repeat('a', 256)]],
    'associative keyword list' => [['key' => 'Aruula']],
    'invalid UTF-8 bytes' => [["\xff"]],
    'too many keywords' => [array_fill(0, 501, 'a')],
]);
dataset('CoverKeywordsTest.fileTitles', static fn (): array => [['File:alpha_Beta.jpg', 'Datei:Alpha Beta.jpg'], ['Bild:001tibi.jpg', 'Datei:001tibi.jpg'], ['001tibi.jpg', 'Datei:001tibi.jpg'], ['Datei:Äther.jpg', 'Datei:Äther.jpg']]);
dataset('CoverKeywordsTest.invalidTitles', static fn (): array => [[''], ['Datei:'], ['File:foo]] OR [[+'], ['File:foo|?Secret'], ['File:<script>'], [str_repeat('x', 256)]]);
test('normalizes unicode whitespace and duplicates without losing display names', function (): void {
    expect(CoverKeywords::values([' Aruula ', 'aruula', "F-17\nAlpha 2", 'Äther', '', '  ']))->toBe(['aruula' => 'Aruula', 'f-17 alpha 2' => 'F-17 Alpha 2', 'äther' => 'Äther']);
});
test('rejects invalid lists', function (array $values): void {
    expect(fn () => CoverKeywords::values($values))->toThrow(InvalidArgumentException::class);
})->with('CoverKeywordsTest.invalidValues');
test('canonicalizes namespace and first letter only', function (string $input, string $expected): void {
    expect(CoverKeywords::fileTitle($input))->toBe($expected);
})->with('CoverKeywordsTest.fileTitles');
test('rejects unsafe titles', function (string $title): void {
    expect(fn () => CoverKeywords::fileTitle($title))->toThrow(InvalidArgumentException::class);
})->with('CoverKeywordsTest.invalidTitles');
