<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgCombatDelivery extends Model
{
    use UsesUtcCombatDates;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['claimed_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    public function combat(): BelongsTo
    {
        return $this->belongsTo(RpgCombat::class, 'rpg_combat_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(RpgCombatDecision::class, 'rpg_combat_decision_id');
    }
}
