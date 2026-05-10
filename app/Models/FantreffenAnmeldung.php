<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Scope a query to only include registrations with t-shirt orders.
     */
    public function scopeMitTshirt($query)
    {
        return $query->where('tshirt_bestellt', true);
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
     * Get the t-shirt price for this registration.
     * T-shirt price is always 25.00€, but the total amount differs:
     * - Members: 25.00€ (t-shirt only)
     * - Guests: 30.00€ (5.00€ guest fee + 25.00€ t-shirt)
     */
    public function getTshirtPrice(): float
    {
        if ($this->veranstaltung && $this->veranstaltung->tshirt_aktiv) {
            return (float) $this->veranstaltung->tshirt_preis;
        }

        return self::TSHIRT_PRICE;
    }

    /**
     * Get the total amount to pay for this registration.
     * - Member without t-shirt: 0.00€ (free)
     * - Member with t-shirt: 25.00€
     * - Guest without t-shirt: 5.00€
     * - Guest with t-shirt: 30.00€ (5.00€ + 25.00€)
     */
    public function getTotalAmount(): float
    {
        if ($this->orga_team) {
            return 0;
        }

        if ($this->veranstaltung && ! $this->veranstaltung->zahlung_aktiv) {
            return 0;
        }

        $amount = 0;
        $guestFee = (float) ($this->veranstaltung?->gastgebuehr ?? self::GUEST_FEE);
        $tshirtPrice = (float) ($this->veranstaltung?->tshirt_preis ?? self::TSHIRT_PRICE);

        if (! $this->ist_mitglied) {
            $amount += $guestFee;
        }

        if ($this->tshirt_bestellt) {
            $amount += $tshirtPrice;
        }

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

        $guestFee = (float) ($this->veranstaltung?->gastgebuehr ?? self::GUEST_FEE);
        $tshirtPrice = (float) ($this->veranstaltung?->tshirt_preis ?? self::TSHIRT_PRICE);
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
