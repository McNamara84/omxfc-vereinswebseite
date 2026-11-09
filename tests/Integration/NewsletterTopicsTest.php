<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\NewsletterAusgabe;
use App\Support\NewsletterTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('normalize adds legacy keys and image defaults', function (): void {
    $topics = NewsletterTopics::normalize([['title' => ' Erstes Thema ', 'content' => 'Text'], ['title' => 'Zweites Thema', 'content' => 'Mehr Text', 'images' => [' newsletter-images/a.jpg ', '', null]]]);
    expect($topics[0]['key'])->toBe('legacy-topic-0')
        ->and($topics[0]['title'])->toBe('Erstes Thema')
        ->and($topics[0]['images'])->toBe([])
        ->and($topics[1]['key'])->toBe('legacy-topic-1')
        ->and($topics[1]['images'])->toBe(['newsletter-images/a.jpg']);
});

test('normalize preserves existing key', function (): void {
    $topics = NewsletterTopics::normalize([['key' => 'topic-123', 'title' => 'Mit Key', 'content' => 'Inhalt', 'images' => ['newsletter-images/test.webp']]]);
    expect($topics[0]['key'])->toBe('topic-123')
        ->and($topics[0]['images'])->toBe(['newsletter-images/test.webp']);
});

test('render html renders markdown and strips unsafe html', function (): void {
    $html = NewsletterTopics::renderHtml("**Stark**\n\n<script>alert('x')</script>");
    expect($html)->toContain('<strong>Stark</strong>')->not->toContain('<script>');
});

test('excerpt returns plain text from rendered markdown', function (): void {
    $excerpt = NewsletterTopics::excerpt("## Titel\n\n**Fetter** Text mit [Link](https://example.com)", 50);
    expect($excerpt)->toContain('Titel')
        ->toContain('Fetter Text mit Link')->not->toContain('**')->not->toContain('[');
});

test('render html normalizes windows line endings', function (): void {
    $html = NewsletterTopics::renderHtml("Erste Zeile\r\nZweite Zeile\r\n\r\nDritte Zeile");
    expect($html)->toContain('Erste Zeile')
        ->toContain('Zweite Zeile')
        ->toContain('Dritte Zeile')->not->toContain("\r");
});

test('ensure distinct persistent keys replaces legacy and duplicate keys', function (): void {
    $topics = NewsletterTopics::ensureDistinctPersistentKeys([['key' => 'legacy-topic-0', 'title' => 'A', 'content' => 'B', 'images' => []], ['key' => 'duplicate-key', 'title' => 'C', 'content' => 'D', 'images' => []], ['key' => 'duplicate-key', 'title' => 'E', 'content' => 'F', 'images' => []]]);
    $keys = array_column($topics, 'key');
    expect(array_unique($keys))->toHaveCount(3)
        ->and($keys)->sequence(fn ($e) => $e->not->toBe('legacy-topic-0'), fn ($e) => $e->toBe('duplicate-key'), fn ($e) => $e->not->toBe('duplicate-key'));
});

test('excerpt uses model cache when model is available', function (): void {
    Cache::flush();
    $ausgabe = NewsletterAusgabe::factory()->create();
    $first = NewsletterTopics::excerpt('**Cache** Text', 80, $ausgabe, 'topic-a');
    $ausgabe->forceFill(['updated_at' => $ausgabe->updated_at->copy()->addSecond()])->saveQuietly();
    $second = NewsletterTopics::excerpt('**Cache** Text', 80, $ausgabe->fresh(), 'topic-a');
    expect($first)->toBe('Cache Text')
        ->and($second)->toBe('Cache Text');
});

test('initial topic uses persistent key', function (): void {
    $topic = NewsletterTopics::initialTopic();
    expect($topic['key'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($topic['title'])->toBe('')
        ->and($topic['content'])->toBe('')
        ->and($topic['images'])->toBe([]);
});
