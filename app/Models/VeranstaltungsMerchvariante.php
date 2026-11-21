<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VeranstaltungsMerchvariante extends Model
{
    use HasFactory;

    #[\Override]
    protected $table = 'veranstaltungs_merchvarianten';

    #[\Override]
    protected $fillable = [
        'veranstaltungs_merchartikel_id',
        'bezeichnung',
        'sort_order',
        'is_active',
    ];

    #[\Override]
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(VeranstaltungsMerchartikel::class, 'veranstaltungs_merchartikel_id');
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(FantreffenAnmeldungMerchartikel::class, 'veranstaltungs_merchvariante_id');
    }

    public function scopeAktiv($query)
    {
        return $query->where('is_active', true);
    }
}
