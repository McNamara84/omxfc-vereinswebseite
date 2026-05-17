<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FantreffenAnmeldung extends Model
{
    /**
     * Pricing constants for the event.
     *
     * Note: T-shirt price is always 25.00€ for both members and guests.
     * Guests pay an additional 5.00€ participation fee (total: 30.00€ with t-shirt).
     */
    public const GUEST_FEE = 5.00;

    public const TSHIRT_PRICE = 25.00;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fantreffen_anmeldungen';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'veranstaltung_id',
        'user_id',
        'vorname',
        'nachname',
        'email',
        'mobile',
        'tshirt_bestellt',
        'tshirt_groesse',
        'payment_status',
        'payment_amount',
        'tshirt_fertig',
        'zahlungseingang',
        'paypal_transaction_id',
        'ist_mitglied',
        'orga_team',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tshirt_bestellt' => 'boolean',
        'tshirt_fertig' => 'boolean',
        'zahlungseingang' => 'boolean',
        'ist_mitglied' => 'boolean',
        'orga_team' => 'boolean',
        'payment_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $anmeldung) {
            if ($anmeldung->veranstaltung_id !== null) {
                return;
            }

            $anmeldung->veranstaltung_id = Veranstaltung::featuredPublic()?->id
                ?? Veranstaltung::query()->orderByDesc('ist_highlight')->value('id');
        });
    }

    /**
     * Get the user that owns the registration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    public function merchartikelBestellungen(): HasMany
    {
        return $this->hasMany(FantreffenAnmeldungMerchartikel::class, 'fantreffen_anmeldung_id')
            ->orderBy('id');
    }

    public function hasMerchBestellungen(): bool
    {
        if ($this->relationLoaded('merchartikelBestellungen')) {
            return $this->merchartikelBestellungen->isNotEmpty();
        }

        return $this->merchartikelBestellungen()->exists();
    }

    public function getMerchandiseTotal(): float
    {
        if ($this->hasMerchBestellungen()) {
            $bestellungen = $this->relationLoaded('merchartikelBestellungen')
                ? $this->merchartikelBestellungen
                : $this->merchartikelBestellungen()->get();

            return (float) $bestellungen->sum(
                fn (FantreffenAnmeldungMerchartikel $bestellung) => (float) $bestellung->preis_zum_bestellzeitpunkt
            );
        }

        if ($this->tshirt_bestellt) {
            return (float) ($this->veranstaltung?->tshirt_preis ?? self::TSHIRT_PRICE);
        }

        return 0.0;
    }

    public function getOrderedMerchandiseAttribute(): Collection
    {
        $bestellungen = $this->relationLoaded('merchartikelBestellungen')
            ? $this->merchartikelBestellungen
            : $this->merchartikelBestellungen()->with(['artikel', 'variante'])->get();

        if ($bestellungen instanceof Collection && $bestellungen->isNotEmpty()) {
            $bestellungen->loadMissing(['artikel', 'variante']);

            return $bestellungen->map(fn (FantreffenAnmeldungMerchartikel $bestellung) => [
                'id' => $bestellung->id,
                'name' => $bestellung->artikel?->bezeichnung ?? 'Merchandise',
                'variant' => $bestellung->variante?->bezeichnung,
                'price' => (float) $bestellung->preis_zum_bestellzeitpunkt,
                'done' => $bestellung->status_erledigt,
            ]);
        }

        if (! $this->tshirt_bestellt) {
            return collect();
        }

        return collect([[
            'id' => null,
            'name' => 'T-Shirt',
            'variant' => $this->tshirt_groesse,
            'price' => $this->getLegacyTshirtPrice(),
            'done' => $this->tshirt_fertig,
        ]]);
    }

    /**
     * Kompatibilitäts-Scope für bestehende Aufrufer: liefert Anmeldungen mit irgendeiner Merchandise-Bestellung.
     */
    public function scopeMitTshirt($query)
    {
        return $query->mitMerch();
    }

    public function scopeMitMerch($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where('tshirt_bestellt', true)
                ->orWhereHas('merchartikelBestellungen');
        });
    }

    public function scopeOhneMerch($query)
    {
        return $query->where('tshirt_bestellt', false)
            ->whereDoesntHave('merchartikelBestellungen');
    }

    public function scopeMitOffenemMerch($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where(function ($legacyQuery) {
                $legacyQuery->where('tshirt_bestellt', true)
                    ->where('tshirt_fertig', false);
            })->orWhereHas('merchartikelBestellungen', function ($bestellungenQuery) {
                $bestellungenQuery->where('status_erledigt', false);
            });
        });
    }

    public function scopeMitErledigtemMerch($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where(function ($legacyQuery) {
                $legacyQuery->where('tshirt_bestellt', true)
                    ->where('tshirt_fertig', true)
                    ->whereDoesntHave('merchartikelBestellungen');
            })->orWhere(function ($bestellungenQuery) {
                $bestellungenQuery->whereHas('merchartikelBestellungen')
                    ->whereDoesntHave('merchartikelBestellungen', function ($offenQuery) {
                        $offenQuery->where('status_erledigt', false);
                    });
            });
        });
    }

    /**
     * Scope a query to only include member registrations.
     */
    public function scopeMitglieder($query)
    {
        return $query->where('ist_mitglied', true);
    }

    /**
     * Scope a query to only include guest registrations.
     */
    public function scopeGaeste($query)
    {
        return $query->where('ist_mitglied', false);
    }

    /**
     * Scope a query to only include paid registrations.
     */
    public function scopeBezahlt($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopeZahlungAusstehend($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Get the full name of the registrant.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->user && $this->user->vorname && $this->user->nachname) {
            return trim($this->user->vorname.' '.$this->user->nachname);
        }

        if ($this->vorname && $this->nachname) {
            return trim($this->vorname.' '.$this->nachname);
        }

        return $this->email ?? 'N/A';
    }

    /**
     * Get the email of the registrant.
     */
    public function getRegistrantEmailAttribute(): string
    {
        return $this->user?->email ?? $this->email;
    }

    /**
     * Check if payment is required.
     */
    public function requiresPayment(): bool
    {
        return $this->payment_status !== 'free' && $this->payment_amount > 0;
    }

    /**
     * Mark payment as received.
     */
    public function markPaymentReceived(?string $transactionId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'zahlungseingang' => true,
            'paypal_transaction_id' => $transactionId,
        ]);
    }

    /**
     * Get the effective T-shirt price for this registration.
     *
     * Prefers the persisted price from a merchandise order row, then the event configuration,
     * and finally the legacy fallback constant.
     */
    public function getTshirtPrice(): float
    {
        $tshirtBestellung = $this->getOrderedMerchandiseAttribute()->first(
            fn (array $bestellung) => mb_strtolower($bestellung['name']) === 't-shirt'
        );

        if ($tshirtBestellung !== null) {
            return (float) $tshirtBestellung['price'];
        }

        if ($this->veranstaltung && $this->veranstaltung->tshirt_aktiv) {
            return (float) $this->veranstaltung->tshirt_preis;
        }

        return $this->getLegacyTshirtPrice();
    }

    private function getLegacyTshirtPrice(): float
    {
        if ($this->veranstaltung && $this->veranstaltung->tshirt_aktiv) {
            return (float) $this->veranstaltung->tshirt_preis;
        }

        return self::TSHIRT_PRICE;
    }

    /**
     * Get the total amount to pay for this registration.
     *
     * The total is composed of an optional guest fee when event payments are active,
     * plus the sum of all ordered merchandise positions. Orga-team registrations stay free.
     */
    public function getTotalAmount(): float
    {
        if ($this->orga_team) {
            return 0;
        }

        $amount = 0;
        $guestFee = (float) ($this->veranstaltung?->gastgebuehr ?? self::GUEST_FEE);

        if (! $this->ist_mitglied && ($this->veranstaltung?->zahlung_aktiv ?? true)) {
            $amount += $guestFee;
        }

        $amount += $this->getMerchandiseTotal();

        return $amount;
    }

    /**
     * Get the formatted t-shirt price for display.
     * Shows what the user pays for the t-shirt:
     * - Members: "25,00 €"
     * - Guests: "30,00 €" (includes guest fee)
     */
    public function getFormattedTshirtPrice(): string
    {
        if ($this->orga_team) {
            return '0,00 €';
        }

        $guestFee = ($this->veranstaltung?->zahlung_aktiv ?? true)
            ? (float) ($this->veranstaltung?->gastgebuehr ?? self::GUEST_FEE)
            : 0.0;
        $tshirtPrice = $this->getTshirtPrice();
        $price = $this->ist_mitglied ? $tshirtPrice : ($guestFee + $tshirtPrice);

        return number_format($price, 2, ',', '.').' €';
    }

    /**
     * Update payment fields depending on Orga-Team status and existing selections.
     */
    public function syncPaymentForOrgaStatus(bool $isOrgaTeam): void
    {
        $this->orga_team = $isOrgaTeam;

        if ($isOrgaTeam) {
            $this->payment_amount = 0;
            $this->payment_status = 'free';
            $this->zahlungseingang = true;
        } else {
            $amount = $this->getTotalAmount();
            $this->payment_amount = $amount;
            $this->payment_status = $amount > 0 ? 'pending' : 'free';
            $this->zahlungseingang = $amount === 0;
        }

        $this->save();
    }
}
