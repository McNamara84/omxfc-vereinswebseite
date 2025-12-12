<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ReviewComment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property int $book_id
 * @property string $title
 * @property string $content
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Book $book
 * @property-read User $user
 * @property-read Collection<int, ReviewComment> $comments
 */
class Review extends Model
{
    use HasFactory, SoftDeletes;

    private const ALLOWED_HTML_TAGS = '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6>';
    private ?string $formattedContentCache = null;
    private ?string $formattedContentSource = null;

    protected $fillable = [
        'team_id',
        'user_id',
        'book_id',
        'title',
        'content',
    ];

    /**
     * The book that this review is for.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * The user who wrote this review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Comments belonging to this review.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    /**
     * Render review content as sanitized HTML.
     *
     * - Converts Markdown to HTML while stripping unsafe tags.
     * - Filters out event handler attributes and inline styles.
     * - Normalizes links by removing unsafe protocols, disallowing malformed URLs,
     *   and enforcing safe rel attributes.
     * - Returns an empty string for empty or non-meaningful content.
     *
     * Allowed tags are defined in ALLOWED_HTML_TAGS and limited to basic text
     * formatting (paragraphs, emphasis, links, headings, lists, blockquotes,
     * code, and line breaks). Relative links are permitted when they look like
     * local files or paths containing common characters (letters, numbers,
     * dashes, underscores, dots, and slashes) with optional query or fragment
     * parts.
     */
    public function getFormattedContentAttribute(): string
    {
        $markdown = (string) ($this->content ?? '');

        if ($this->formattedContentSource === $markdown && $this->formattedContentCache !== null) {
            return $this->formattedContentCache;
        }

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
        ]);

        $html = strip_tags($html, self::ALLOWED_HTML_TAGS);

        $textOnly = trim(strip_tags($html));

        if ($textOnly === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8" ?>' . '<div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (!$loaded) {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();

            return $html;
        }

        $container = $dom->getElementsByTagName('div')->item(0);
        if ($container === null) {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();

            return $html;
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
                $this->sanitizeLink($element);
            }
        }

        libxml_use_internal_errors($previousLibxmlSetting);
        libxml_clear_errors();

        $fragment = '';

        foreach ($container->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }

        $this->formattedContentSource = $markdown;
        $this->formattedContentCache = $fragment;

        return $fragment;
    }

    private function sanitizeLink(\DOMElement $element): void
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

                    if (!in_array($normalizedScheme, ['http', 'https', 'mailto'], true)) {
                        $element->removeAttribute('href');
                    }
                } else {
                    $trimmedHref = ltrim($href);
                    $isHashLink = Str::startsWith($trimmedHref, '#');
                    $isRelativePath = Str::startsWith($trimmedHref, ['/', './', '../']);
                    $looksLikeFile = preg_match('/^[A-Za-z0-9._\-][A-Za-z0-9._\-\/]*([?#][^\s]*)?$/', $trimmedHref) === 1;

                    if (Str::startsWith($trimmedHref, '//') || (!$isHashLink && !$isRelativePath && !$looksLikeFile)) {
                        $element->removeAttribute('href');
                    }
                }
            }
        }

        $element->setAttribute('rel', 'noopener noreferrer');
    }
}

