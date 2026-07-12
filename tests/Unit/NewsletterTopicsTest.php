<?php

namespace Tests\Unit;

use App\Models\NewsletterAusgabe;
use App\Support\NewsletterTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NewsletterTopicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_adds_legacy_keys_and_image_defaults(): void
    {
        $topics = NewsletterTopics::normalize([
            [
                'title' => ' Erstes Thema ',
                'content' => 'Text',
            ],
            [
                'title' => 'Zweites Thema',
                'content' => 'Mehr Text',
                'images' => [' newsletter-images/a.jpg ', '', null],
            ],
        ]);

        $this->assertSame('legacy-topic-0', $topics[0]['key']);
        $this->assertSame('Erstes Thema', $topics[0]['title']);
        $this->assertSame([], $topics[0]['images']);

        $this->assertSame('legacy-topic-1', $topics[1]['key']);
        $this->assertSame(['newsletter-images/a.jpg'], $topics[1]['images']);
    }

    public function test_normalize_preserves_existing_key(): void
    {
        $topics = NewsletterTopics::normalize([
            [
                'key' => 'topic-123',
                'title' => 'Mit Key',
                'content' => 'Inhalt',
                'images' => ['newsletter-images/test.webp'],
            ],
        ]);

        $this->assertSame('topic-123', $topics[0]['key']);
        $this->assertSame(['newsletter-images/test.webp'], $topics[0]['images']);
    }

    public function test_render_html_renders_markdown_and_strips_unsafe_html(): void
    {
        $html = NewsletterTopics::renderHtml("**Stark**\n\n<script>alert('x')</script>");

        $this->assertStringContainsString('<strong>Stark</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_excerpt_returns_plain_text_from_rendered_markdown(): void
    {
        $excerpt = NewsletterTopics::excerpt("## Titel\n\n**Fetter** Text mit [Link](https://example.com)", 50);

        $this->assertStringContainsString('Titel', $excerpt);
        $this->assertStringContainsString('Fetter Text mit Link', $excerpt);
        $this->assertStringNotContainsString('**', $excerpt);
        $this->assertStringNotContainsString('[', $excerpt);
    }

    public function test_render_html_normalizes_windows_line_endings(): void
    {
        $html = NewsletterTopics::renderHtml("Erste Zeile\r\nZweite Zeile\r\n\r\nDritte Zeile");

        $this->assertStringContainsString('Erste Zeile', $html);
        $this->assertStringContainsString('Zweite Zeile', $html);
        $this->assertStringContainsString('Dritte Zeile', $html);
        $this->assertStringNotContainsString("\r", $html);
    }

    public function test_ensure_distinct_persistent_keys_replaces_legacy_and_duplicate_keys(): void
    {
        $topics = NewsletterTopics::ensureDistinctPersistentKeys([
            ['key' => 'legacy-topic-0', 'title' => 'A', 'content' => 'B', 'images' => []],
            ['key' => 'duplicate-key', 'title' => 'C', 'content' => 'D', 'images' => []],
            ['key' => 'duplicate-key', 'title' => 'E', 'content' => 'F', 'images' => []],
        ]);

        $keys = array_column($topics, 'key');

        $this->assertCount(3, array_unique($keys));
        $this->assertNotSame('legacy-topic-0', $keys[0]);
        $this->assertSame('duplicate-key', $keys[1]);
        $this->assertNotSame('duplicate-key', $keys[2]);
    }

    public function test_excerpt_uses_model_cache_when_model_is_available(): void
    {
        Cache::flush();

        $ausgabe = NewsletterAusgabe::factory()->create();

        $first = NewsletterTopics::excerpt('**Cache** Text', 80, $ausgabe, 'topic-a');

        $ausgabe->forceFill(['updated_at' => $ausgabe->updated_at->copy()->addSecond()])->saveQuietly();

        $second = NewsletterTopics::excerpt('**Cache** Text', 80, $ausgabe->fresh(), 'topic-a');

        $this->assertSame('Cache Text', $first);
        $this->assertSame('Cache Text', $second);
    }

    public function test_initial_topic_uses_persistent_key(): void
    {
        $topic = NewsletterTopics::initialTopic();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $topic['key']);
        $this->assertSame('', $topic['title']);
        $this->assertSame('', $topic['content']);
        $this->assertSame([], $topic['images']);
    }
}
