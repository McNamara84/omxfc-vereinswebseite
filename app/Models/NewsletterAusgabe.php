<?php

namespace App\Models;

use App\Enums\NewsletterAusgabeStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsletterAusgabe extends Model
{
    use HasFactory;

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
            if (filled($newsletterAusgabe->slug)) {
                return;
            }

            $baseSlug = Str::slug($newsletterAusgabe->subject) ?: 'newsletter';
            $slug = $baseSlug;
            $counter = 2;

            while (static::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $newsletterAusgabe->slug = $slug;
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
        return $query->where('status', NewsletterAusgabeStatus::Veroeffentlicht->value);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', NewsletterAusgabeStatus::Entwurf->value);
    }

    public function scopeVisibleInArchivFor(Builder $query, Role $role): Builder
    {
        return $query
            ->published()
            ->whereJsonContains('recipient_roles', $role->value);
    }

    public function isVisibleInArchivFor(Role $role): bool
    {
        return $this->status === NewsletterAusgabeStatus::Veroeffentlicht
            && in_array($role->value, $this->recipient_roles ?? [], true);
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
}