<?php

namespace App\Models;

use App\Enums\AuktionsStatus;
use App\Support\Euro;
use App\Support\SanitizedMarkdown;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Auktion extends Model
{
    use HasFactory;

    protected $table = 'auktionen';

    protected $fillable = [
        'titel',
        'beschreibung_markdown',
        'startbetrag_cent',
        'mindestschritt_cent',
        'status',
        'verkauft_an_user_id',
        'verkauft_gebot_id',
        'verkauft_at',
    ];

    protected $casts = [
        'startbetrag_cent' => 'integer',
        'mindestschritt_cent' => 'integer',
        'status' => AuktionsStatus::class,
        'verkauft_at' => 'datetime',
    ];

    public function gebote(): HasMany
    {
        return $this->hasMany(AuktionGebot::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function verkauftAnUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verkauft_an_user_id');
    }

    public function verkauftesGebot(): BelongsTo
    {
        return $this->belongsTo(AuktionGebot::class, 'verkauft_gebot_id');
    }

    public function hoechstgebotRelation(): HasOne
    {
        return $this->hasOne(AuktionGebot::class)->ofMany([
            'betrag_cent' => 'max',
            'created_at' => 'max',
            'id' => 'max',
        ]);
    }

    public function scopeAktiv($query)
    {
        return $query->whereIn('status', [
            AuktionsStatus::Laufend->value,
            AuktionsStatus::ZumErsten->value,
            AuktionsStatus::ZumZweiten->value,
        ]);
    }

    public function scopeArchiviert($query)
    {
        return $query->whereIn('status', [
            AuktionsStatus::Verkauft->value,
            AuktionsStatus::NichtVerkauft->value,
        ]);
    }

    public function getHtmlBeschreibungAttribute(): string
    {
        $markdown = (string) ($this->beschreibung_markdown ?? '');
        $cacheKey = SanitizedMarkdown::cacheKey($this, 'auktion', 'html_beschreibung', $markdown);

        if ($cacheKey !== null) {
            return Cache::rememberForever($cacheKey, fn (): string => SanitizedMarkdown::render($markdown, [
                'allow_unsafe_links' => false,
            ]));
        }

        return SanitizedMarkdown::render($markdown, [
            'allow_unsafe_links' => false,
        ]);
    }

    public function getFormatierterStartbetragAttribute(): string
    {
        return Euro::format($this->startbetrag_cent);
    }

    public function getFormatierterMindestschrittAttribute(): string
    {
        return Euro::format($this->mindestschritt_cent);
    }

    public function hasGebote(): bool
    {
        if (array_key_exists('gebote_count', $this->attributes)) {
            return (int) $this->attributes['gebote_count'] > 0;
        }

        if ($this->relationLoaded('gebote')) {
            return $this->gebote->isNotEmpty();
        }

        return $this->gebote()->exists();
    }

    public function hoechstgebot(): ?AuktionGebot
    {
        if ($this->relationLoaded('hoechstgebotRelation')) {
            return $this->getRelation('hoechstgebotRelation');
        }

        if ($this->relationLoaded('gebote')) {
            return $this->sortierteGebote($this->gebote)->first();
        }

        return $this->gebote()
            ->orderByDesc('betrag_cent')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function gebotsverlauf(): Collection
    {
        if ($this->relationLoaded('gebote')) {
            return $this->gebote;
        }

        return $this->gebote()->get();
    }

    public function aktuellerPreisCent(): int
    {
        return $this->hoechstgebot()?->betrag_cent ?? $this->startbetrag_cent;
    }

    public function aktuellerPreis(): string
    {
        return Euro::format($this->aktuellerPreisCent());
    }

    public function naechstesMindestgebotCent(): int
    {
        $hoechstgebot = $this->hoechstgebot();

        if (! $hoechstgebot) {
            return $this->startbetrag_cent;
        }

        return $hoechstgebot->betrag_cent + $this->mindestschritt_cent;
    }

    public function naechstesMindestgebot(): string
    {
        return Euro::format($this->naechstesMindestgebotCent());
    }

    public function kannGeboteAnnehmen(): bool
    {
        return ($this->status ?? AuktionsStatus::Laufend)->erlaubtGebote();
    }

    public function basisFelderGesperrt(): bool
    {
        return $this->hasGebote();
    }

    public function kannGeloeschtWerden(): bool
    {
        return ! $this->hasGebote();
    }

    public function kannZumErstenAufgerufenWerden(): bool
    {
        return $this->status === AuktionsStatus::Laufend;
    }

    public function kannZumZweitenAufgerufenWerden(): bool
    {
        return $this->status === AuktionsStatus::ZumErsten;
    }

    public function kannVerkauftWerden(): bool
    {
        return $this->status === AuktionsStatus::ZumZweiten && $this->hoechstgebot() !== null;
    }

    public function kannAlsNichtVerkauftBeendetWerden(): bool
    {
        return $this->status === AuktionsStatus::ZumZweiten;
    }

    private function sortierteGebote(Collection $gebote): Collection
    {
        return $gebote->sort(function (AuktionGebot $left, AuktionGebot $right): int {
            $amountCompare = $right->betrag_cent <=> $left->betrag_cent;

            if ($amountCompare !== 0) {
                return $amountCompare;
            }

            $timestampCompare = ($right->created_at?->getTimestamp() ?? 0) <=> ($left->created_at?->getTimestamp() ?? 0);

            if ($timestampCompare !== 0) {
                return $timestampCompare;
            }

            return $right->id <=> $left->id;
        })->values();
    }
}
