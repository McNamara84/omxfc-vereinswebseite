<?php

namespace App\Models;

use App\Enums\NewsletterAusgabeStatus;
use App\Enums\Role;
use App\Support\NewsletterTopics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsletterAusgabe extends Model
{
    use HasFactory;

    private const FALLBACK_SLUG = 'newsletter';

    private const MAX_SLUG_LENGTH = 255;

    protected $table = 'newsletter_ausgaben';

    protected $fillable = [
        'subject',
        'slug',
        'topics',
        'recipient_roles',
        'status',
        'sent_at',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'recipient_roles' => 'array',
            'status' => NewsletterAusgabeStatus::class,
            'sent_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterAusgabe $newsletterAusgabe) {
            $newsletterAusgabe->slug = static::generateUniqueSlug(
                $newsletterAusgabe->slug,
                $newsletterAusgabe->subject,
            );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NewsletterAusgabeStatus::Veroeffentlicht);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', NewsletterAusgabeStatus::Entwurf);
    }

    public function scopeVisibleInArchivFor(Builder $query, Role $role): Builder
    {
        $query->published();

        if (self::hasArchivBypassRole($role)) {
            return $query;
        }

        return $query->whereJsonContains('recipient_roles', $role->value);
    }

    public function isVisibleInArchivFor(Role $role): bool
    {
        if ($this->status !== NewsletterAusgabeStatus::Veroeffentlicht) {
            return false;
        }

        if (self::hasArchivBypassRole($role)) {
            return true;
        }

        return in_array($role->value, $this->recipient_roles ?? [], true);
    }

    /**
     * @param  array{key?: string, content?: string}|array<string, mixed>  $topic
     */
    public function excerptForTopic(array $topic, int $limit = 220, string $fallbackVariant = 'topic'): string
    {
        $variant = is_string($topic['key'] ?? null) && trim((string) $topic['key']) !== ''
            ? trim((string) $topic['key'])
            : $fallbackVariant;

        return NewsletterTopics::excerpt((string) ($topic['content'] ?? ''), $limit, $this, $variant);
    }

    /**
     * @return array<int, Role>
     */
    public static function recipientRoles(): array
    {
        return [
            Role::Mitglied,
            Role::Ehrenmitglied,
            Role::Kassenwart,
            Role::Vorstand,
            Role::Admin,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function recipientRoleValues(): array
    {
        return array_map(
            fn (Role $role): string => $role->value,
            self::recipientRoles(),
        );
    }

    public static function defaultRecipientRole(): Role
    {
        return Role::Mitglied;
    }

    public static function generateUniqueSlug(?string $preferredSlug, ?string $fallbackSubject = null, ?self $ignore = null): string
    {
        $baseSlug = static::baseSlug($preferredSlug, $fallbackSubject);
        $slug = static::truncateSlug($baseSlug);
        $counter = 2;

        while (static::slugExists($slug, $ignore)) {
            $suffix = '-'.$counter;
            $slug = static::truncateSlug($baseSlug, strlen($suffix)).$suffix;
            $counter++;
        }

        return $slug;
    }

    private static function baseSlug(?string $preferredSlug, ?string $fallbackSubject = null): string
    {
        $baseSlug = Str::slug((string) $preferredSlug);

        if ($baseSlug === '') {
            $baseSlug = Str::slug((string) $fallbackSubject);
        }

        return $baseSlug !== '' ? $baseSlug : self::FALLBACK_SLUG;
    }

    private static function truncateSlug(string $slug, int $reservedSuffixLength = 0): string
    {
        $maxLength = max(1, self::MAX_SLUG_LENGTH - $reservedSuffixLength);

        return rtrim(Str::substr($slug, 0, $maxLength), '-');
    }

    private static function slugExists(string $slug, ?self $ignore = null): bool
    {
        return static::query()
            ->when($ignore, fn (Builder $query) => $query->whereKeyNot($ignore->getKey()))
            ->where('slug', $slug)
            ->exists();
    }

    private static function hasArchivBypassRole(Role $role): bool
    {
        return in_array($role, [Role::Kassenwart, Role::Vorstand, Role::Admin], true);
    }
}