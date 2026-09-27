<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgCombatParticipant extends Model
{
    use UsesUtcCombatDates;

    protected $guarded = [];

    protected $hidden = ['snapshot', 'snapshot_hash'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'side' => 'integer', 'character_revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $participant): void {
            if ($participant->isDirty(['snapshot', 'snapshot_hash', 'character_revision'])) {
                throw new \LogicException('Kampfstände sind unveränderlich.');
            }
        });
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function combat(): BelongsTo
    {
        return $this->belongsTo(RpgCombat::class, 'rpg_combat_id');
    }
}
