<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaddraxikonStreakRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at_epoch' => 'integer', 'last_evaluated_epoch' => 'integer', 'last_rechecked_epoch' => 'integer'];
    }
}
