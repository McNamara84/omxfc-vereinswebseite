<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantreffenAnmeldung extends Model
{
    /**
     * Pricing constants for the event.
     */
    public const GUEST_FEE = 5.00;
    public const TSHIRT_PRICE_MEMBER = 25.00;
    public const TSHIRT_PRICE_GUEST = 30.00;

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
        'payment_amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the registration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            return trim($this->user->vorname . ' ' . $this->user->nachname);
        }

        if ($this->vorname && $this->nachname) {
            return trim($this->vorname . ' ' . $this->nachname);
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
    public function markPaymentReceived(string $transactionId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'zahlungseingang' => true,
            'paypal_transaction_id' => $transactionId,
        ]);
    }

    /**
     * Get the t-shirt price for this registration.
     */
    public function getTshirtPrice(): float
    {
        return $this->ist_mitglied ? self::TSHIRT_PRICE_MEMBER : self::TSHIRT_PRICE_GUEST;
    }

    /**
     * Get the formatted t-shirt price for this registration.
     */
    public function getFormattedTshirtPrice(): string
    {
        return number_format($this->getTshirtPrice(), 2, ',', '.') . ' €';
    }
}
