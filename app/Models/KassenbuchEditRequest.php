<?php

namespace App\Models;

use App\Enums\KassenbuchEditReasonType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $kassenbuch_entry_id
 * @property int $requested_by
 * @property int|null $processed_by
 * @property string $reason_type
 * @property string|null $reason_text
 * @property string $status
 * @property string|null $rejection_reason
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KassenbuchEntry $entry
 * @property-read User $requester
 * @property-read User|null $processor
 */
class KassenbuchEditRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'kassenbuch_entry_id',
        'requested_by',
        'processed_by',
        'reason_type',
        'reason_text',
        'status',
        'rejection_reason',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Get the kassenbuch entry this request belongs to.
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(KassenbuchEntry::class, 'kassenbuch_entry_id');
    }

    /**
     * Get the user who requested the edit.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who processed the request.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get the reason type as enum.
     */
    public function getReasonTypeEnum(): ?KassenbuchEditReasonType
    {
        return KassenbuchEditReasonType::tryFrom($this->reason_type);
    }

    /**
     * Get the formatted reason (type label + optional text).
     */
    public function getFormattedReason(): string
    {
        $reasonEnum = $this->getReasonTypeEnum();
        $label = $reasonEnum?->label() ?? $this->reason_type;

        if ($this->reason_text) {
            return $label.': '.$this->reason_text;
        }

        return $label;
    }
}
