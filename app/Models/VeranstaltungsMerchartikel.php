<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VeranstaltungsMerchartikel extends Model
{
    use HasFactory;

    protected $table = 'veranstaltungs_merchartikel';

    protected $fillable = [
        'veranstaltung_id',
        'bezeichnung',
        'beschreibung',
        'preis',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'preis' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    public function varianten(): HasMany
    {
        return $this->hasMany(VeranstaltungsMerchvariante::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(FantreffenAnmeldungMerchartikel::class, 'veranstaltungs_merchartikel_id');
    }

    public function scopeAktiv($query)
    {
        return $query->where('is_active', true);
    }

    public function requiresVariant(): bool
    {
        if ($this->relationLoaded('varianten')) {
            return $this->varianten->contains(fn (VeranstaltungsMerchvariante $variante) => $variante->is_active);
        }

        return $this->varianten()->where('is_active', true)->exists();
    }
}