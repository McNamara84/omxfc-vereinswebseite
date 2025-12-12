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
        $html = Str::markdown($this->content, [
            'html_input' => 'strip',
        ]);

        $html = strip_tags($html, '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6>');

        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8" ?>' . '<div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        if (!$loaded) {
            libxml_clear_errors();
            return $html;
        }
        foreach ($dom->getElementsByTagName('a') as $a) {
            $a->setAttribute('rel', 'noopener noreferrer');
        }
        libxml_clear_errors();

        $fragment = '';
        $container = $dom->getElementsByTagName('div')->item(0);
        if ($container === null) {
            return $html;
        }

        foreach ($container->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }

        return $fragment;
    }
}

