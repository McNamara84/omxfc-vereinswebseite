<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaddraxikonStreakDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['streak_length' => 'integer', 'target_points' => 'integer', 'settled_points' => 'integer', 'calculation_version' => 'integer', 'valid_count' => 'integer', 'pending_count' => 'integer', 'starts_at_epoch' => 'integer'];
    }

    protected function activityDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => CarbonImmutable::parse($value, 'UTC')->startOfDay(),
            set: fn ($value) => CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MaddraxikonStreakBooking::class, 'streak_day_id');
    }
}
