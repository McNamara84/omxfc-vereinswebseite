<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaddraxikonStreakBooking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['points_delta' => 'integer', 'previous_points' => 'integer', 'target_points' => 'integer', 'calculation_version' => 'integer', 'source_revision_ids' => 'array'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonStreakDay::class, 'streak_day_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
