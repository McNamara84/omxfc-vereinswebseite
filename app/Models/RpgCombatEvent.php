<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;

class RpgCombatEvent extends Model
{
    use UsesUtcCombatDates;

    #[\Override]
    public $timestamps = false;

    #[\Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
