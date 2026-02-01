<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für hochgeladene Romantexte im Kompendium.
 *
 * @property int $id
 * @property string $dateiname
 * @property string $dateipfad
 * @property string $serie
 * @property int $roman_nr
 * @property string $titel
 * @property string|null $zyklus
 * @property \Carbon\Carbon $hochgeladen_am
 * @property int $hochgeladen_von
 * @property \Carbon\Carbon|null $indexiert_am
 * @property string $status
 * @property string|null $fehler_nachricht
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class KompendiumRoman extends Model
{
    protected $table = 'kompendium_romane';

    protected $fillable = [
        'dateiname',
        'dateipfad',
        'serie',
        'roman_nr',
        'titel',
        'zyklus',
        'hochgeladen_am',
        'hochgeladen_von',
        'indexiert_am',
        'status',
        'fehler_nachricht',
    ];

    protected $casts = [
        'hochgeladen_am' => 'datetime',
        'indexiert_am' => 'datetime',
        'roman_nr' => 'integer',
    ];

    /**
     * Der User, der den Roman hochgeladen hat.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hochgeladen_von');
    }

    /**
     * Prüft, ob der Roman indexiert ist.
     */
    public function istIndexiert(): bool
    {
        return $this->status === 'indexiert';
    }

    /**
     * Prüft, ob die Indexierung gerade läuft.
     */
    public function istInBearbeitung(): bool
    {
        return $this->status === 'indexierung_laeuft';
    }

    /**
     * Prüft, ob ein Fehler vorliegt.
     */
    public function hatFehler(): bool
    {
        return $this->status === 'fehler';
    }

    /**
     * Scope für indexierte Romane.
     */
    public function scopeIndexiert($query)
    {
        return $query->where('status', 'indexiert');
    }

    /**
     * Scope für nicht-indexierte Romane.
     */
    public function scopeNichtIndexiert($query)
    {
        return $query->where('status', 'hochgeladen');
    }
}
