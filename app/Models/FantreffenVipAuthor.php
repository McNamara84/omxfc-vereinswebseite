<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantreffenVipAuthor extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fantreffen_vip_authors';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'veranstaltung_id',
        'name',
        'pseudonym',
        'is_active',
        'is_tentative',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_tentative' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $author) {
            if ($author->veranstaltung_id !== null) {
                return;
            }

            $author->veranstaltung_id = Veranstaltung::featuredPublic()?->id
                ?? Veranstaltung::query()->orderByDesc('ist_highlight')->value('id');
        });
    }

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    /**
     * Scope a query to only include active VIP authors.
     */
    public function scopeActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the display name for the author.
     * Shows pseudonym in quotes if available, otherwise just the name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->pseudonym) {
            return $this->name.' („'.$this->pseudonym.'")';
        }

        return $this->name;
    }
}
