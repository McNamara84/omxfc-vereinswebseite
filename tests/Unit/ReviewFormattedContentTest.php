<?php

namespace Tests\Unit;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFormattedContentTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertStringNotContainsStringIgnoringCase('script', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('alert', $formatted);
    }

    public function test_it_handles_empty_and_whitespace_only_content(): void
    {
        $emptyReview = new Review(['content' => '']);
        $whitespaceReview = new Review(['content' => "   \n   "]); 

        $this->assertSame('', $emptyReview->formatted_content);
        $this->assertSame('', $whitespaceReview->formatted_content);
    }

    public function test_it_handles_malformed_markdown_gracefully(): void
    {
        $review = new Review(['content' => "**Fetter Text ohne Ende\n*Unvollständige Aufzählung"]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('Fetter Text', $formatted);
        $this->assertStringContainsString('Unvollständige Aufzählung', $formatted);
    }

    public function test_it_sanitizes_additional_xss_vectors_case_insensitive(): void
    {
        $review = new Review(['content' => "Unsafe [link](JaVaScRiPt:alert('XSS')) raw <a href=\"HTTP://example.com\" OnClick=\"alert('xss')\">Click</a> and data [uri](data:text/html,alert('boom'))"]);

        $formatted = $review->formatted_content;

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('data:text/html', $formatted);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $formatted);
        $this->assertStringContainsString('<a rel="noopener noreferrer">link</a>', $formatted);
    }

    public function test_it_handles_deeply_nested_structures_and_long_content(): void
    {
        $nestedMarkdown = "> Quote\n> \n> 1. Eins\n>    - Unterpunkt\n>      - Noch tiefer\n\n";
        $longContent = str_repeat('Langer Inhalt ', 500);
        $review = new Review(['content' => $nestedMarkdown . $longContent]);

        $formatted = $review->formatted_content;

        $this->assertStringContainsString('<blockquote>', $formatted);
        $this->assertStringContainsString('<ol>', $formatted);
        $this->assertStringContainsString('Langer Inhalt', $formatted);
        $this->assertGreaterThan(1000, strlen($formatted));
    }
}
