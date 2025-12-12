<?php

namespace Tests\Unit;

use App\Models\Review;
use PHPUnit\Framework\TestCase;

class ReviewFormattedContentTest extends TestCase
{
    public function test_it_formats_paragraphs_with_spacing(): void
    {
        $review = new Review(['content' => "Erster Absatz\n\nZweiter Absatz"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<p>Erster Absatz</p>', $formatted);
        $this->assertStringContainsString('<p>Zweiter Absatz</p>', $formatted);
    }

    public function test_it_allows_richer_markdown_structures(): void
    {
        $review = new Review(['content' => "## Überschrift\n\n- Punkt eins\n- Punkt zwei\n\n> Zitat\n\n`Code`"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<h2>Überschrift</h2>', $formatted);
        $this->assertStringContainsString('<ul>', $formatted);
        $this->assertStringContainsString('<li>Punkt eins</li>', $formatted);
        $this->assertStringContainsString('<blockquote>', $formatted);
        $this->assertStringContainsString('<p>Zitat</p>', $formatted);
        $this->assertStringContainsString('<code>Code</code>', $formatted);
    }

    public function test_it_strips_disallowed_html_and_sanitizes_links(): void
    {
        $review = new Review(['content' => "Click [here](https://example.com)\n\n<script>alert('xss')</script>"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<a href="https://example.com" rel="noopener noreferrer">here</a>', $formatted);
        $this->assertStringNotContainsString('script', $formatted);
        $this->assertStringNotContainsString('alert', $formatted);
    }
}
