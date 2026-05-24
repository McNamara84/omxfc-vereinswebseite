<?php

namespace App\Models;

use App\Enums\KassenbuchEditRequestType;
use App\Enums\KassenbuchEntryType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property int $created_by
 * @property Carbon $buchungsdatum
 * @property float $betrag
 * @property string $beschreibung
 * @property KassenbuchEntryType $typ
 * @property int|null $last_edited_by
 * @property Carbon|null $last_edited_at
 * @property string|null $last_edit_reason
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read User|null $lastEditor
 * @property-read KassenbuchEditRequest|null $pendingRequest
 * @property-read KassenbuchEditRequest|null $approvedRequest
 * @property-read KassenbuchEditRequest|null $pendingEditRequest
 * @property-read KassenbuchEditRequest|null $approvedEditRequest
 * @property-read KassenbuchEditRequest|null $pendingDeleteRequest
 */
class KassenbuchEntry extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'created_by',
        'buchungsdatum',
        'betrag',
        'beschreibung',
        'typ',
        'last_edited_by',
        'last_edited_at',
        'last_edit_reason',
    ];

    protected $casts = [
        'buchungsdatum' => 'date',
        'betrag' => 'decimal:2',
        'typ' => KassenbuchEntryType::class,
        'last_edited_at' => 'datetime',
    ];

    /**
     * Get the team that owns the entry.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last edited the entry.
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Get all edit requests for this entry.
     */
    public function editRequests(): HasMany
    {
        return $this->hasMany(KassenbuchEditRequest::class);
    }

    /**
     * Get the pending request for this entry regardless of request type.
     */
    public function pendingRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)
            ->where('status', KassenbuchEditRequest::STATUS_PENDING);
    }

    /**
     * Get the approved request for this entry regardless of request type.
     */
    public function approvedRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)
            ->where('status', KassenbuchEditRequest::STATUS_APPROVED);
    }

    /**
     * Get the pending edit request for this entry.
     */
    public function pendingEditRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)
            ->where('status', KassenbuchEditRequest::STATUS_PENDING)
            ->where('request_type', KassenbuchEditRequestType::Edit->value);
    }

    /**
     * Get the approved edit request for this entry.
     */
    public function approvedEditRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)
            ->where('status', KassenbuchEditRequest::STATUS_APPROVED)
            ->where('request_type', KassenbuchEditRequestType::Edit->value);
    }

    /**
     * Get the pending delete request for this entry.
     */
    public function pendingDeleteRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)
            ->where('status', KassenbuchEditRequest::STATUS_PENDING)
            ->where('request_type', KassenbuchEditRequestType::Delete->value);
    }

    /**
     * Check if this entry has a pending edit request.
     * Uses eager-loaded relation if available to avoid N+1 queries.
     */
    public function hasPendingEditRequest(): bool
    {
        if ($this->relationLoaded('pendingEditRequest')) {
            return $this->getRelation('pendingEditRequest') !== null;
        }

        return $this->pendingEditRequest()->exists();
    }

    /**
     * Check if this entry has an approved edit request.
     * Uses eager-loaded relation if available to avoid N+1 queries.
     */
    public function hasApprovedEditRequest(): bool
    {
        if ($this->relationLoaded('approvedEditRequest')) {
            return $this->getRelation('approvedEditRequest') !== null;
        }

        return $this->approvedEditRequest()->exists();
    }

    /**
     * Check if this entry has any pending request.
     * Uses eager-loaded relation if available to avoid N+1 queries.
     */
    public function hasPendingRequest(): bool
    {
        if ($this->relationLoaded('pendingRequest')) {
            return $this->getRelation('pendingRequest') !== null;
        }

        return $this->pendingRequest()->exists();
    }

    /**
     * Check if this entry has any approved request.
     * Uses eager-loaded relation if available to avoid N+1 queries.
     */
    public function hasApprovedRequest(): bool
    {
        if ($this->relationLoaded('approvedRequest')) {
            return $this->getRelation('approvedRequest') !== null;
        }

        return $this->approvedRequest()->exists();
    }

    /**
     * Check if this entry has a pending delete request.
     * Uses eager-loaded relation if available to avoid N+1 queries.
     */
    public function hasPendingDeleteRequest(): bool
    {
        if ($this->relationLoaded('pendingDeleteRequest')) {
            return $this->getRelation('pendingDeleteRequest') !== null;
        }

        return $this->pendingDeleteRequest()->exists();
    }

    /**
     * Check if this entry can be edited (has approved request).
     */
    public function canBeEdited(): bool
    {
        return $this->hasApprovedEditRequest();
    }

    /**
     * Check if this entry was edited before.
     */
    public function wasEdited(): bool
    {
        return $this->last_edited_at !== null;
    }
}
