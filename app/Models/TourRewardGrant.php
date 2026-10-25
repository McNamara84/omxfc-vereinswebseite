<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourRewardGrant extends Model
{
    protected $fillable = [
        'user_id', 'reward_key', 'tour_assignment_id', 'tour_key', 'tour_version',
        'user_point_id', 'points', 'awarded_at',
    ];

    protected function casts(): array
    {
        return ['tour_version' => 'integer', 'points' => 'integer', 'awarded_at' => 'datetime'];
    }
}
