<?php

namespace App\Models;

use App\Enums\AuktionsStatus;
use App\Support\Euro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Auktion extends Model
{
    use HasFactory;

    private const ALLOWED_HTML_TAGS = '<p><strong><em><a><ul><ol><li><blockquote><code><pre><br><h1><h2><h3><h4><h5><h6>';

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
        return $this->renderSanitizedBeschreibung((string) ($this->beschreibung_markdown ?? ''));
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

    private function sanitizeLink(\DOMElement $element): void
    {
        $href = trim($element->getAttribute('href'));

        if ($href !== '') {
            if (preg_match('/^(javascript|data|vbscript):/i', $href)) {
                $element->removeAttribute('href');
            } else {
                $scheme = parse_url($href, PHP_URL_SCHEME);

                if ($scheme === false) {
                    $element->removeAttribute('href');
                } elseif ($scheme !== null) {
                    $normalizedScheme = strtolower($scheme);

                    if (! in_array($normalizedScheme, ['http', 'https', 'mailto'], true)) {
                        $element->removeAttribute('href');
                    }
                } else {
                    $trimmedHref = ltrim($href);
                    $isHashLink = Str::startsWith($trimmedHref, '#');
                    $isRelativePath = Str::startsWith($trimmedHref, ['/', './', '../']);
                    $looksLikeFile = preg_match('/^[A-Za-z_][A-Za-z0-9._\-\/]*([?#][^\s]*)?$/', $trimmedHref) === 1;

                    if (Str::startsWith($trimmedHref, '//') || (! $isHashLink && ! $isRelativePath && ! $looksLikeFile)) {
                        $element->removeAttribute('href');
                    }
                }
            }
        }

        $element->setAttribute('rel', 'noopener noreferrer');
    }

    private function renderSanitizedBeschreibung(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = strip_tags($html, self::ALLOWED_HTML_TAGS);

        $textOnly = trim(strip_tags($html));

        if ($textOnly === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousLibxmlSetting = libxml_use_internal_errors(true);

        try {
            $wrappedHtml = '<div>'.$html.'</div>';
            $encodedHtml = mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8');

            $loaded = $dom->loadHTML(
                '<?xml version="1.0" encoding="UTF-8"?>'.$encodedHtml,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $this->safeHtmlFallback($html);
            }

            $container = $dom->getElementsByTagName('div')->item(0);

            if ($container === null) {
                return $this->safeHtmlFallback($html);
            }

            foreach ($dom->getElementsByTagName('*') as $element) {
                if ($element->hasAttributes()) {
                    $attributesToRemove = [];

                    foreach ($element->attributes as $attribute) {
                        $name = strtolower($attribute->nodeName);

                        if (str_starts_with($name, 'on') || $name === 'style') {
                            $attributesToRemove[] = $attribute->nodeName;
                        }
                    }

                    foreach ($attributesToRemove as $attributeName) {
                        $element->removeAttribute($attributeName);
                    }
                }

                if (strtolower($element->nodeName) === 'a') {
                    $this->sanitizeLink($element);
                }
            }

            $fragment = '';

            foreach ($container->childNodes as $child) {
                $fragment .= $dom->saveHTML($child);
            }

            return $fragment;
        } finally {
            libxml_use_internal_errors($previousLibxmlSetting);
            libxml_clear_errors();
        }
    }

    private function safeHtmlFallback(string $html): string
    {
        $text = trim(strip_tags($html));

        if ($text === '') {
            return '';
        }

        return nl2br(e($text));
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
