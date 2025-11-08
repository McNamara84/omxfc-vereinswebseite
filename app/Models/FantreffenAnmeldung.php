<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantreffenAnmeldung extends Model
{
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
        if ($this->user) {
            return $this->user->vorname . ' ' . $this->user->nachname;
        }

        return $this->vorname . ' ' . $this->nachname;
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
}
