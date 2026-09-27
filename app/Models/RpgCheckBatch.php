<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpgCheckBatch extends Model
{
    protected $guarded = [];

    protected $hidden = ['submission_key', 'submission_hash'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RpgCheck::class);
    }
}
