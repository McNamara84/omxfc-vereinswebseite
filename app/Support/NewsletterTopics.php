<?php

namespace App\Support;

use Illuminate\Support\Str;

final class NewsletterTopics
{
    private const LEGACY_KEY_PREFIX = 'legacy-topic-';

    /**
     * @param  array<int, mixed>|null  $topics
     * @return array<int, array{key: string, title: string, content: string, images: array<int, string>}>
     */
    public static function normalize(?array $topics): array
    {
        if (! is_array($topics)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($topics) as $index => $topic) {
            $normalized[] = self::normalizeTopic($topic, $index);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $topic
     * @return array{key: string, title: string, content: string, images: array<int, string>}
     */
    public static function normalizeTopic(mixed $topic, ?int $index = null): array
    {
        $topic = is_array($topic) ? $topic : [];

        $key = trim((string) ($topic['key'] ?? ''));

        if ($key === '') {
            $key = $index !== null
                ? self::legacyKey($index)
                : self::generatePersistentKey();
        }

        $images = is_array($topic['images'] ?? null) ? $topic['images'] : [];
        $images = array_values(array_filter(array_map(
            static fn (mixed $path): string => is_string($path) ? trim($path) : '',
            $images,
        )));

        return [
            'key' => $key,
            'title' => trim((string) ($topic['title'] ?? '')),
            'content' => (string) ($topic['content'] ?? ''),
            'images' => $images,
        ];
    }

    /**
     * @return array{key: string, title: string, content: string, images: array<int, string>}
     */
    public static function initialTopic(): array
    {
        return [
            'key' => self::generatePersistentKey(),
            'title' => '',
            'content' => '',
            'images' => [],
        ];
    }

    public static function renderHtml(?string $markdown): string
    {
        $markdown = preg_replace('/(?<!\n)\n(?!\n)/', "  \n", (string) ($markdown ?? ''));

        return SanitizedMarkdown::render((string) $markdown);
    }

    public static function excerpt(?string $markdown, int $limit = 220): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(self::renderHtml($markdown))) ?? '');

        return Str::limit($text, $limit);
    }

    public static function usesLegacyKey(?string $key): bool
    {
        return is_string($key) && str_starts_with($key, self::LEGACY_KEY_PREFIX);
    }

    public static function generatePersistentKey(): string
    {
        return (string) Str::uuid();
    }

    private static function legacyKey(int $index): string
    {
        return self::LEGACY_KEY_PREFIX.$index;
    }
}