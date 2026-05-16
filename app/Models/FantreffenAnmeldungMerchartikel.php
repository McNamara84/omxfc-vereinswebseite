<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantreffenAnmeldungMerchartikel extends Model
{
    use HasFactory;

    protected $table = 'fantreffen_anmeldung_merchartikel';

    protected $fillable = [
        'fantreffen_anmeldung_id',
        'veranstaltungs_merchartikel_id',
        'veranstaltungs_merchvariante_id',
        'preis_zum_bestellzeitpunkt',
        'status_erledigt',
        'status_erledigt_am',
    ];

    protected $casts = [
        'preis_zum_bestellzeitpunkt' => 'decimal:2',
        'status_erledigt' => 'boolean',
        'status_erledigt_am' => 'datetime',
    ];

    public function anmeldung(): BelongsTo
    {
        return $this->belongsTo(FantreffenAnmeldung::class, 'fantreffen_anmeldung_id');
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(VeranstaltungsMerchartikel::class, 'veranstaltungs_merchartikel_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(VeranstaltungsMerchvariante::class, 'veranstaltungs_merchvariante_id');
    }
}