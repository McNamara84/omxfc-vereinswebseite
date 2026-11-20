<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RpgAmmunitionProfile extends Model
{
    #[\Override]
    public $timestamps = false;

    #[\Override]
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Munitionsprofile sind unveränderlich.'));
        static::deleting(fn () => throw new \LogicException('Munitionsprofile sind unveränderlich.'));
    }
}
