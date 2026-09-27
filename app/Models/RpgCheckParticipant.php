<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgCheckParticipant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['modifiers' => 'array', 'rolled_at' => 'datetime',
            ...array_fill_keys(['position', 'character_revision', 'attribute_value', 'skill_value',
                'modifier_total', 'base_total', 'die_one', 'die_two', 'raw_total', 'total', 'margin'], 'integer')];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(RpgCheck::class, 'rpg_check_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }
}
