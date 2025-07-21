<?php

namespace App\Models;

use Laravel\Jetstream\Membership as JetstreamMembership;
use Carbon\Carbon;

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
}
