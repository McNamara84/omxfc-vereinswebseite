<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RpgInventoryTransaction extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_on' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Inventarbuchungen sind unveränderlich.'));
        static::deleting(fn () => throw new \LogicException('Inventarbuchungen sind unveränderlich.'));
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RpgInventoryEntry::class, 'transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of');
    }

    public static function kinds(): array
    {
        return ['initialize' => 'Bestand übernehmen', 'grant' => 'Vergabe / Fund', 'loss' => 'Verlust / Verbrauch',
            'correct' => 'Bestand korrigieren', 'equip' => 'Ausrüstung anpassen', 'money' => 'Geldbewegung',
            'purchase' => 'Kauf', 'sale' => 'Verkauf', 'swap' => 'Tausch', 'regenerate' => 'Energie regenerieren',
            'reversal' => 'Rückbuchung', 'advancement' => 'High-Tech-Vorteil', 'item_version_change' => 'Gegenstandsversion wechseln'];
    }

    public function label(): string
    {
        return self::kinds()[$this->kind] ?? $this->kind;
    }
}
