<?php

namespace Tests\Integration;

use App\Models\Fanfiction;
use App\Support\SanitizedMarkdown;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarkdownEncodingTest extends TestCase
{
    #[DataProvider('renderers')]
    public function test_unicode_and_entities_survive_sanitizing_without_reviving_unsafe_html(bool $fanfiction): void
    {
        $markdown = "# Grüße 🌍\n\n**ÄÖÜ** Café — 中文 & Zeichen [Lesen](https://example.test/?a=1&b=2)\n\n<script>alert(1)</script><img src=x onerror=alert(1)>";
        $html = $fanfiction
            ? (new Fanfiction)->renderFormattedContent($markdown, [])
            : SanitizedMarkdown::render($markdown);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);

        $this->assertStringContainsString('Grüße 🌍', $dom->textContent);
        $this->assertStringContainsString('ÄÖÜ Café — 中文 & Zeichen', $dom->textContent);
        $this->assertSame('https://example.test/?a=1&b=2', $dom->getElementsByTagName('a')->item(0)->getAttribute('href'));
        $this->assertSame(0, $dom->getElementsByTagName('script')->length);
        $this->assertSame(0, $dom->getElementsByTagName('img')->length);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public static function renderers(): iterable
    {
        yield 'shared markdown' => [false];
        yield 'fanfiction' => [true];
    }
}
