<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgExperienceAward extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['criteria', 'reason'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'points' => 'integer', 'calculated_points' => 'integer'];
    }

    public function adventure(): BelongsTo
    {
        return $this->belongsTo(RpgAdventure::class, 'rpg_adventure_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }
}
