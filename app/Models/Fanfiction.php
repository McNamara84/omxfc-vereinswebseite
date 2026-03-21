<?php

namespace App\Models;

use App\Enums\FanfictionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int $created_by
 * @property string $title
 * @property string $author_name
 * @property string $content
 * @property array|null $photos
 * @property FanfictionStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $author
 * @property-read User $creator
 * @property-read Collection<int, FanfictionComment> $comments
 * @property-read string $formatted_content
 * @property-read string $teaser
 */
class Fanfiction extends Model
{
    use HasFactory, SoftDeletes;

    private const ALLOWED_HTML_TAGS = '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6><figure><figcaption><img>';

    private const BILD_TAG_PATTERN = '/\[bild:(\d+)(?::(links|rechts|zentriert))?(?::([^\]]+))?\]/i';

    private const TEASER_LENGTH = 400;

    protected $fillable = [
        'team_id',
        'user_id',
        'created_by',
        'title',
        'author_name',
        'content',
        'photos',
        'status',
        'published_at',
        'reward_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => FanfictionStatus::class,
    ];

    /**
     * The team this fanfiction belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The reward linked to this fanfiction for purchase-based unlocking.
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /**
     * The member who wrote this fanfiction (optional).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who created/uploaded this fanfiction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the display name for the author.
     * Returns the linked user's name or the author_name field.
     */
    public function getAuthorDisplayNameAttribute(): string
    {
        if ($this->author) {
            return $this->author->name;
        }

        return $this->author_name ?? 'Unbekannt';
    }

    /**
     * Comments belonging to this fanfiction.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FanfictionComment::class);
    }

    /**
     * Scope to filter only published fanfictions.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FanfictionStatus::Published);
    }

    /**
     * Scope to filter fanfictions by team.
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get the photos attribute as array.
     *
     * @param  mixed  $value
     * @return array<int, string>
     */
    public function getPhotosAttribute($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        $photos = is_array($decoded) ? $decoded : [];

        return $this->sanitizePhotoPaths($photos);
    }

    /**
     * Set the photos attribute.
     *
     * @param  mixed  $value
     */
    public function setPhotosAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['photos'] = null;

            return;
        }

        if (! is_array($value)) {
            $decoded = json_decode($value ?? '[]', true);
            $value = is_array($decoded) ? $decoded : [];
        }

        $this->attributes['photos'] = json_encode($this->sanitizePhotoPaths($value));
    }

    /**
     * Sanitize photo paths.
     *
     * @param  array<int, mixed>  $photos
     * @return array<int, string>
     */
    private function sanitizePhotoPaths(array $photos): array
    {
        $normalized = [];

        foreach ($photos as $photo) {
            if (! is_string($photo)) {
                continue;
            }

            $normalizedPath = ltrim(trim($photo), '/');

            if ($normalizedPath === '') {
                continue;
            }

            $normalized[] = $normalizedPath;
        }

        return $normalized;
    }

    /**
     * Get a teaser (first ~400 characters) of the content.
     */
    public function getTeaserAttribute(): string
    {
        $plainText = strip_tags($this->formatted_content);
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
        $plainText = preg_replace('/\s+/', ' ', trim($plainText));

        if (mb_strlen($plainText) <= self::TEASER_LENGTH) {
            return $plainText;
        }

        $teaser = mb_substr($plainText, 0, self::TEASER_LENGTH);

        // Cut at last word boundary
        $lastSpace = mb_strrpos($teaser, ' ');
        if ($lastSpace !== false && $lastSpace > self::TEASER_LENGTH - 50) {
            $teaser = mb_substr($teaser, 0, $lastSpace);
        }

        return $teaser.'…';
    }

    /**
     * Render content as sanitized HTML (like Review model).
     */
    public function getFormattedContentAttribute(): string
    {
        $markdown = (string) ($this->content ?? '');

        $cacheKey = $this->formattedContentCacheKey();

        if ($cacheKey !== null) {
            return Cache::rememberForever($cacheKey, function () use ($markdown) {
                return $this->renderFormattedContent($markdown);
            });
        }

        return $this->renderFormattedContent($markdown);
    }

    /**
     * Render Markdown to sanitized HTML with defense-in-depth escaping.
     */
    public function renderFormattedContent(string $markdown, ?array $photos = null): string
    {
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

        try {
            $wrappedHtml = '<div>'.$html.'</div>';
            $encodedHtml = mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8');

            $loaded = $dom->loadHTML(
                '<?xml version="1.0" encoding="UTF-8"?>'.$encodedHtml,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $this->safeFallback($html);
            }

            $container = $dom->getElementsByTagName('div')->item(0);
            if ($container === null) {
                return $this->safeFallback($html);
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

            $fragment = '';

            foreach ($container->childNodes as $child) {
                $fragment .= $dom->saveHTML($child);
            }

            return $this->replaceBildTags($fragment, $photos ?? $this->photos);
        } finally {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();
        }
    }

    /**
     * Replace [bild:N:position:caption] tags with <figure> elements.
     *
     * @param  array<int, string>  $photos
     */
    private function replaceBildTags(string $html, array $photos): string
    {
        if ($photos === [] || preg_match(self::BILD_TAG_PATTERN, $html) !== 1) {
            // Remove any bild tags if there are no photos
            return preg_replace(self::BILD_TAG_PATTERN, '', $html);
        }

        return preg_replace_callback(self::BILD_TAG_PATTERN, function (array $matches) use ($photos) {
            $index = (int) $matches[1] - 1; // Convert 1-based to 0-based
            $position = strtolower($matches[2] ?? 'zentriert') ?: 'zentriert';
            $caption = trim($matches[3] ?? '');

            if ($index < 0 || $index >= count($photos)) {
                return ''; // Invalid index: silently remove
            }

            $photoPath = $photos[$index];
            $url = asset('storage/'.$photoPath);
            $escapedUrl = e($url);
            $escapedCaption = e($caption);
            $altText = $escapedCaption ?: e($this->title ?? 'Fanfiction-Bild');

            $positionClass = match ($position) {
                'links' => 'fanfiction-bild--links',
                'rechts' => 'fanfiction-bild--rechts',
                default => 'fanfiction-bild--zentriert',
            };

            $figcaptionHtml = $caption !== '' ? "\n  <figcaption>{$escapedCaption}</figcaption>" : '';

            return "<figure class=\"fanfiction-bild {$positionClass}\">\n  <img src=\"{$escapedUrl}\" alt=\"{$altText}\" loading=\"lazy\">{$figcaptionHtml}\n</figure>";
        }, $html);
    }

    /**
     * Get 0-based indices of photos referenced by [bild:N] tags in the content.
     *
     * @return array<int>
     */
    public function getReferencedPhotoIndices(): array
    {
        $content = (string) ($this->content ?? '');
        $indices = [];

        if (preg_match_all(self::BILD_TAG_PATTERN, $content, $matches)) {
            foreach ($matches[1] as $match) {
                $index = (int) $match - 1;
                if ($index >= 0 && $index < count($this->photos)) {
                    $indices[] = $index;
                }
            }
        }

        return array_unique($indices);
    }

    /**
     * Get photos that are NOT referenced by [bild:N] tags in the content.
     *
     * @return array<int, string>
     */
    public function getUnreferencedPhotos(): array
    {
        $referenced = $this->getReferencedPhotoIndices();
        $unreferenced = [];

        foreach ($this->photos as $index => $photo) {
            if (! in_array($index, $referenced, true)) {
                $unreferenced[] = $photo;
            }
        }

        return $unreferenced;
    }

    /**
     * Normalize anchor attributes and strip unsafe href values.
     */
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

    /**
     * Provide a safe, escaped fallback fragment when DOM parsing fails.
     */
    private function safeFallback(string $html): string
    {
        $text = trim(strip_tags($html));

        if ($text === '') {
            return '';
        }

        return nl2br(e($text));
    }

    /**
     * Build a cache key for formatted content.
     */
    private function formattedContentCacheKey(): ?string
    {
        if (! $this->exists || $this->getKey() === null) {
            return null;
        }

        $updatedAtAttribute = $this->getAttribute('updated_at');

        if ($updatedAtAttribute === null) {
            return null;
        }

        $updatedAt = $updatedAtAttribute instanceof Carbon
            ? $updatedAtAttribute
            : $this->asDateTime($updatedAtAttribute);

        if ($updatedAt === null) {
            return null;
        }

        $timestamp = $updatedAt->format('Uu');
        $contentHash = md5((string) $this->content);

        return sprintf('fanfiction:%s:formatted:%s:%s', $this->getKey(), $timestamp, $contentHash);
    }
}
