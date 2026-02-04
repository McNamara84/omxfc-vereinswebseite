<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $origin
 * @property string $destination
 * @property int $travel_duration
 * @property int $mission_duration
 * @property Carbon|null $started_at
 * @property Carbon|null $arrival_at
 * @property Carbon|null $mission_ends_at
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
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
