<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SanitizedMarkdown
{
    private const ALLOWED_HTML_TAGS = '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6>';

    public static function render(string $markdown, array $markdownOptions = []): string
    {
        $html = Str::markdown($markdown, array_merge([
            'html_input' => 'strip',
        ], $markdownOptions));

        $html = strip_tags($html, self::ALLOWED_HTML_TAGS);

        $textOnly = trim(strip_tags($html));

        if ($textOnly === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousLibxmlSetting = libxml_use_internal_errors(true);

        try {
            $wrappedHtml = '<div>'.$html.'</div>';
            $encodedHtml = mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8');

            $loaded = $dom->loadHTML(
                '<?xml version="1.0" encoding="UTF-8"?>'.$encodedHtml,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return self::safeFallback($html);
            }

            $container = $dom->getElementsByTagName('div')->item(0);

            if ($container === null) {
                return self::safeFallback($html);
            }

            foreach ($dom->getElementsByTagName('*') as $element) {
                if ($element->hasAttributes()) {
                    $attributesToRemove = [];

                    foreach ($element->attributes as $attribute) {
                        $name = strtolower($attribute->nodeName);

                        if (str_starts_with($name, 'on') || $name === 'style') {
                            $attributesToRemove[] = $attribute->nodeName;
                        }
                    }

                    foreach ($attributesToRemove as $attributeName) {
                        $element->removeAttribute($attributeName);
                    }
                }

                if (strtolower($element->nodeName) === 'a') {
                    self::sanitizeLink($element);
                }
            }

            $fragment = '';

            foreach ($container->childNodes as $child) {
                $fragment .= $dom->saveHTML($child);
            }

            return $fragment;
        } finally {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();
        }
    }

    public static function cacheKey(Model $model, string $namespace, string $variant, string $content): ?string
    {
        if (! $model->exists || $model->getKey() === null) {
            return null;
        }

        $updatedAtAttribute = $model->getAttribute('updated_at');

        if ($updatedAtAttribute === null) {
            return null;
        }

        $updatedAt = $updatedAtAttribute instanceof Carbon
            ? $updatedAtAttribute
            : $model->asDateTime($updatedAtAttribute);

        if ($updatedAt === null) {
            return null;
        }

        return sprintf(
            '%s:%s:%s:%s:%s',
            $namespace,
            $model->getKey(),
            $variant,
            $updatedAt->format('Uu'),
            md5($content)
        );
    }

    private static function sanitizeLink(\DOMElement $element): void
    {
        $href = trim($element->getAttribute('href'));

        if ($href !== '') {
            if (preg_match('/^(javascript|data|vbscript):/i', $href)) {
                $element->removeAttribute('href');
            } else {
                $scheme = parse_url($href, PHP_URL_SCHEME);

                if ($scheme === false) {
                    $element->removeAttribute('href');
                } elseif ($scheme !== null) {
                    $normalizedScheme = strtolower($scheme);

                    if (! in_array($normalizedScheme, ['http', 'https', 'mailto'], true)) {
                        $element->removeAttribute('href');
                    }
                } else {
                    $trimmedHref = ltrim($href);
                    $isHashLink = Str::startsWith($trimmedHref, '#');
                    $isRelativePath = Str::startsWith($trimmedHref, ['/', './', '../']);
                    $looksLikeFile = preg_match('/^[A-Za-z_][A-Za-z0-9._\-\/]*([?#][^\s]*)?$/', $trimmedHref) === 1;

                    if (Str::startsWith($trimmedHref, '//') || (! $isHashLink && ! $isRelativePath && ! $looksLikeFile)) {
                        $element->removeAttribute('href');
                    }
                }
            }
        }

        $element->setAttribute('rel', 'noopener noreferrer');
    }

    private static function safeFallback(string $html): string
    {
        $text = trim(strip_tags($html));

        if ($text === '') {
            return '';
        }

        return nl2br(e($text));
    }
}
