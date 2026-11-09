<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Fanfiction;
use App\Support\SanitizedMarkdown;
use Tests\TestCase;

pest()->extend(TestCase::class);
dataset('MarkdownEncodingTest.renderers', static function (): iterable {
    yield 'shared markdown' => [false];
    yield 'fanfiction' => [true];
});
test('unicode and entities survive sanitizing without reviving unsafe html', function (bool $fanfiction): void {
    $markdown = "# Grüße 🌍\n\n**ÄÖÜ** Café — 中文 & Zeichen [Lesen](https://example.test/?a=1&b=2)\n\n<script>alert(1)</script><img src=x onerror=alert(1)>";
    $html = $fanfiction ? (new Fanfiction)->renderFormattedContent($markdown, []) : SanitizedMarkdown::render($markdown);
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);
    expect($dom->textContent)->toContain('Grüße 🌍')
        ->toContain('ÄÖÜ Café — 中文 & Zeichen')
        ->and($dom->getElementsByTagName('a')->item(0)->getAttribute('href'))->toBe('https://example.test/?a=1&b=2')
        ->and($dom->getElementsByTagName('script')->length)->toBe(0)
        ->and($dom->getElementsByTagName('img')->length)->toBe(0)
        ->and($html)->not->toContain('onerror');
})->with('MarkdownEncodingTest.renderers');
