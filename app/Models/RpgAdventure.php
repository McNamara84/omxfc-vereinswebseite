<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpgAdventure extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_on' => 'date', 'minutes' => 'integer', 'cycle_bonus' => 'integer'];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(RpgExperienceAward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
