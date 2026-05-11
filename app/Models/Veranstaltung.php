<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Veranstaltung extends Model
{
    use HasFactory;

    protected $table = 'veranstaltungen';

    protected $fillable = [
        'titel',
        'slug',
        'status',
        'veranstaltungsart',
        'untertitel',
        'teaser',
        'beschreibung',
        'datum_von',
        'datum_bis',
        'ort_name',
        'ort_adresse',
        'maps_url',
        'anmeldung_aktiv',
        'anmeldung_start',
        'anmeldung_ende',
        'zahlung_aktiv',
        'tshirt_aktiv',
        'tshirt_deadline',
        'vip_autoren_aktiv',
        'gastgebuehr',
        'tshirt_preis',
        'benachrichtigungs_email',
        'seo_title',
        'seo_description',
        'sort_order',
        'ist_highlight',
    ];

    protected $casts = [
        'datum_von' => 'datetime',
        'datum_bis' => 'datetime',
        'anmeldung_aktiv' => 'boolean',
        'anmeldung_start' => 'datetime',
        'anmeldung_ende' => 'datetime',
        'zahlung_aktiv' => 'boolean',
        'tshirt_aktiv' => 'boolean',
        'tshirt_deadline' => 'datetime',
        'vip_autoren_aktiv' => 'boolean',
        'gastgebuehr' => 'decimal:2',
        'tshirt_preis' => 'decimal:2',
        'ist_highlight' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function anmeldungen(): HasMany
    {
        return $this->hasMany(FantreffenAnmeldung::class);
    }

    public function abschnitte(): HasMany
    {
        return $this->hasMany(VeranstaltungsAbschnitt::class)->orderBy('sort_order');
    }

    public function vipAutoren(): HasMany
    {
        return $this->hasMany(FantreffenVipAuthor::class)->orderBy('sort_order');
    }

    public function scopeVeroeffentlicht($query)
    {
        return $query->where('status', 'veroeffentlicht');
    }

    public function scopeOeffentlichSichtbar($query)
    {
        return $query->whereIn('status', ['veroeffentlicht', 'archiviert']);
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this->status, ['veroeffentlicht', 'archiviert'], true);
    }

    public function isRegistrationOpen(): bool
    {
        if (! $this->anmeldung_aktiv) {
            return false;
        }

        $now = now();

        if ($this->anmeldung_start && $now->lt($this->anmeldung_start)) {
            return false;
        }

        if ($this->anmeldung_ende && $now->gt($this->anmeldung_ende)) {
            return false;
        }

        return true;
    }

    public function kontaktEmail(): string
    {
        return $this->benachrichtigungs_email
            ?: config('services.paypal.fantreffen_email', 'vorstand@maddrax-fanclub.de');
    }

    public function getHtmlBeschreibungAttribute(): string
    {
        return (string) Str::markdown((string) ($this->beschreibung ?? ''), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public static function featuredPublic(): ?self
    {
        return static::query()
            ->veroeffentlicht()
            ->orderByDesc('ist_highlight')
            ->orderBy('datum_von')
            ->orderBy('sort_order')
            ->first();
    }
}