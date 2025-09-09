<?php

namespace App\Models;

use App\Enums\KassenbuchEntryType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $created_by
 * @property Carbon $buchungsdatum
 * @property float $betrag
 * @property string $beschreibung
 * @property KassenbuchEntryType $typ
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $creator
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
    ];

    protected $casts = [
        'buchungsdatum' => 'date',
        'betrag' => 'decimal:2',
        'typ' => KassenbuchEntryType::class,
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
}
