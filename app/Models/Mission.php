<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mission extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'origin',
        'destination',
        'travel_duration',
        'mission_duration',
        'started_at',
        'arrival_at',
        'mission_ends_at',
        'completed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'arrival_at' => 'datetime',
        'mission_ends_at' => 'datetime',
        'completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
