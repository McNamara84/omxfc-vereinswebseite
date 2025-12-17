<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        'name',
        'pseudonym',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

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
