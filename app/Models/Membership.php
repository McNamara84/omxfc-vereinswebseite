<?php

namespace App\Models;

use App\Services\Memberships\MembershipContext;
use Carbon\Carbon;
use Laravel\Jetstream\Membership as JetstreamMembership;

/**
 * @property int $user_id
 * @property int $team_id
 * @property string|null $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Membership extends JetstreamMembership
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    protected static function booted(): void
    {
        $invalidate = fn (self $membership) => app(MembershipContext::class)->invalidateUser((int) $membership->user_id);
        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
