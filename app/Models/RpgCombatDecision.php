<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgCombatDecision extends Model
{
    use UsesUtcCombatDates;

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['response', 'response_hash', 'context'];

    protected function casts(): array
    {
        return ['side' => 'integer', 'controller_side' => 'integer', 'token' => 'integer', 'context' => 'array',
            'response' => 'array', 'automatic' => 'boolean', 'opened_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function combat(): BelongsTo
    {
        return $this->belongsTo(RpgCombat::class, 'rpg_combat_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'prepare' => 'Startausrüstung wählen', 'initiative' => 'Initiative würfeln', 'action' => 'Handlung wählen',
            'defense' => 'Verteidigen', 'damage' => 'Schaden würfeln', 'fumble' => 'Patzer abfangen',
            'strength' => 'Niederwerfen widerstehen', 'resistance' => 'Entwaffnen widerstehen',
            'psychic_resistance' => 'Psychisch widerstehen', 'technology' => 'Benutzungsprobe', 'ruling' => 'Regelfrage entscheiden',
            default => 'Entscheidung treffen',
        };
    }
}
