<?php

namespace App\Models;

use App\Support\SanitizedMarkdown;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    public function maddraxikonRating(): HasOne
    {
        return $this->hasOne(MaddraxikonReviewRating::class);
    }

    public function visibleMaddraxikonRating(): ?MaddraxikonReviewRating
    {
        if (! config('maddraxikon.features.ratings_enabled', false)) {
            return null;
        }

        $rating = $this->relationLoaded('maddraxikonRating')
            ? $this->getRelation('maddraxikonRating')
            : $this->maddraxikonRating()->first();

        if (! $rating instanceof MaddraxikonReviewRating) {
            return null;
        }

        $user = $this->relationLoaded('user')
            ? $this->getRelation('user')
            : $this->user()->first();
        $book = $this->relationLoaded('book')
            ? $this->getRelation('book')
            : $this->book()->first();

        if (! $user instanceof User || ! $book instanceof Book) {
            return null;
        }

        $link = $user->relationLoaded('maddraxikonAccountLink')
            ? $user->getRelation('maddraxikonAccountLink')
            : $user->maddraxikonAccountLink()->first();
        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $consentVersion = (string) config('maddraxikon.consent_version');
        $staleAfter = max(
            15,
            (int) config('maddraxikon.ratings.stale_after_minutes', 60)
        );

        if (
            ! $link?->isActiveForWikiAndConsent($wikiKey, $consentVersion)
            || $rating->rating < 1
            || $rating->rating > 5
            || $rating->synced_at === null
            || $rating->synced_at->isBefore(now()->subMinutes($staleAfter))
            || $rating->user_id !== $this->user_id
            || $rating->book_id !== $this->book_id
            || $rating->account_link_id !== $link->id
            || $rating->wiki_user_id !== $link->wiki_user_id
            || $rating->maddraxikon_page_id !== $book->maddraxikon_page_id
        ) {
            return null;
        }

        return $rating;
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
     * Markdown is rendered with `html_input => strip` to drop raw HTML before
     * parsing; `strip_tags` is applied afterward as defense-in-depth in case the
     * Markdown parser behavior changes in future versions.
     *
     * Allowed tags are defined in ALLOWED_HTML_TAGS and limited to basic text
     * formatting (paragraphs, emphasis, links, headings, lists, blockquotes,
     * code, and line breaks). Relative links are permitted when they look like
     * local files or paths containing common characters (letters, numbers,
     * dashes, underscores, dots, and slashes) with optional query or fragment
     * parts, or when they begin with a hash for in-page anchors.
     */
    public function getFormattedContentAttribute(): string
    {
        $markdown = (string) ($this->content ?? '');

        $cacheKey = SanitizedMarkdown::cacheKey($this, 'review', 'formatted', $markdown);

        if ($cacheKey !== null) {
            return Cache::rememberForever($cacheKey, function () use ($markdown) {
                return SanitizedMarkdown::render($markdown);
            });
        }

        return SanitizedMarkdown::render($markdown);
    }
}
