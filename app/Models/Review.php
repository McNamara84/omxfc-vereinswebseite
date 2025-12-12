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

    public function getFormattedContentAttribute(): string
    {
        $markdown = (string) ($this->content ?? '');

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
        ]);

        $html = strip_tags($html, '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6>');
        $html = preg_replace('/on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html) ?? '';
        $html = preg_replace('/\bjavascript:/i', '', $html) ?? '';

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

            return $textOnly === '' ? '' : $html;
        }

        $container = $dom->getElementsByTagName('div')->item(0);
        if ($container === null) {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();

            return $textOnly === '' ? '' : $html;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element->hasAttributes()) {
                $attributesToRemove = [];

                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->nodeName);

                    if (str_starts_with($name, 'on') || $name === 'style') {
                        $attributesToRemove[] = $name;
                    }
                }

                foreach ($attributesToRemove as $attributeName) {
                    $element->removeAttribute($attributeName);
                }
            }
        }

        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));

            if ($href !== '') {
                if (preg_match('/^(javascript|data|vbscript):/i', $href)) {
                    $a->removeAttribute('href');
                } else {
                    $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
                    $isRelative = Str::startsWith($href, ['/', './', '../', '#']);

                    if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) {
                        $a->removeAttribute('href');
                    } elseif ($scheme === '' && !$isRelative) {
                        $a->removeAttribute('href');
                    }
                }
            }

            $a->setAttribute('rel', 'noopener noreferrer');
        }

        libxml_use_internal_errors($previousLibxmlSetting);
        libxml_clear_errors();

        $fragment = '';

        foreach ($container->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }

        return $fragment;
    }
}

