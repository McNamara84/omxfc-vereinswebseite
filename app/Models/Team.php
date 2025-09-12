<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Illuminate\Support\Collection;
use App\Models\Todo;
use App\Models\UserPoint;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Jetstream\Team as JetstreamTeam;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $personal_team
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Todo> $todos
 * @property-read Collection<int, UserPoint> $userPoints
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    public const MEMBERS_TEAM_CACHE_KEY = 'team.members';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'personal_team',
        'description',
        'email',
        'meeting_schedule',
        'logo_path',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Prüft, ob ein User eine bestimmte Rolle im Team hat.
     */
    public function hasUserWithRole(User $user, string $role): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', $role)
            ->exists();
    }

    /**
     * Get the todos for the team.
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * Get the user points for the team.
     */
    public function userPoints(): HasMany
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Retrieve the "Mitglieder" team if it exists.
     *
     * The result is cached for an hour when present. Missing teams are not
     * cached to avoid persisting a null value.
     */
    public static function membersTeam(): ?self
    {
        $cached = Cache::get(self::MEMBERS_TEAM_CACHE_KEY);

        if ($cached instanceof self) {
            return $cached;
        }

        $team = self::where('name', 'Mitglieder')->first();

        if ($team) {
            Cache::put(self::MEMBERS_TEAM_CACHE_KEY, $team, now()->addHour());
        }

        return $team;
    }

    public static function clearMembersTeamCache(): void
    {
        Cache::forget(self::MEMBERS_TEAM_CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::updated(function (self $team) {
            if (
                $team->wasChanged('name') &&
                ($team->getOriginal('name') === 'Mitglieder' || $team->name === 'Mitglieder')
            ) {
                self::clearMembersTeamCache();
            }
        });

        static::deleted(function (self $team) {
            if ($team->getOriginal('name') === 'Mitglieder' || $team->name === 'Mitglieder') {
                self::clearMembersTeamCache();
            }
        });
    }
}
