<?php

namespace App\Models;

use App\Enums\KassenbuchEntryType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read User|null $lastEditor
 * @property-read KassenbuchEditRequest|null $pendingEditRequest
 * @property-read KassenbuchEditRequest|null $approvedEditRequest
 */
class KassenbuchEntry extends Model
{
    use HasFactory;

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
     * Get the pending edit request for this entry.
     */
    public function pendingEditRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)->where('status', KassenbuchEditRequest::STATUS_PENDING);
    }

    /**
     * Get the approved edit request for this entry.
     */
    public function approvedEditRequest(): HasOne
    {
        return $this->hasOne(KassenbuchEditRequest::class)->where('status', KassenbuchEditRequest::STATUS_APPROVED);
    }

    /**
     * Check if this entry has a pending edit request.
     */
    public function hasPendingEditRequest(): bool
    {
        return $this->pendingEditRequest()->exists();
    }

    /**
     * Check if this entry has an approved edit request.
     */
    public function hasApprovedEditRequest(): bool
    {
        return $this->approvedEditRequest()->exists();
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
