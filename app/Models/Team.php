<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\Carbon;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

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
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected static ?self $resolvedMembersTeam = null;

    public const MEMBERS_TEAM_CACHE_KEY = 'team.members';

    public const MEMBERS_TEAM_ID_CACHE_KEY = 'team.members.id';

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
     * Get all team members except applicants ("Anwärter").
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivotNotIn('role', [Role::Anwaerter->value]);
    }

    /**
     * Retrieve the "Mitglieder" team if it exists.
     *
     * The resolved team is cached by ID indefinitely across requests and the
     * hydrated model is reused statically within the current request. Missing
     * teams are not cached to avoid persisting a null value.
     */
    public static function membersTeam(): ?self
    {
        if (
            self::$resolvedMembersTeam instanceof self
            && self::$resolvedMembersTeam->name === 'Mitglieder'
            && Cache::get(self::MEMBERS_TEAM_ID_CACHE_KEY) === self::$resolvedMembersTeam->id
        ) {
            return self::$resolvedMembersTeam;
        }

        $cached = Cache::get(self::MEMBERS_TEAM_CACHE_KEY);

        if ($cached instanceof self && $cached->name === 'Mitglieder') {
            Cache::forever(self::MEMBERS_TEAM_ID_CACHE_KEY, $cached->id);
            Cache::forget(self::MEMBERS_TEAM_CACHE_KEY);

            return self::$resolvedMembersTeam = $cached;
        }

        Cache::forget(self::MEMBERS_TEAM_CACHE_KEY);

        $teamId = Cache::get(self::MEMBERS_TEAM_ID_CACHE_KEY);

        $team = null;

        if ($teamId) {
            $team = self::query()->find($teamId);

            if ($team?->name !== 'Mitglieder') {
                $team = null;
                Cache::forget(self::MEMBERS_TEAM_ID_CACHE_KEY);
            }
        }

        if (! $team) {
            $team = self::query()
                ->where('name', 'Mitglieder')
                ->get()
                ->first(
                    static fn (self $candidate): bool => $candidate->name === 'Mitglieder'
                );
        }

        if ($team) {
            Cache::forever(self::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

            self::$resolvedMembersTeam = $team;
        }

        return $team;
    }

    public static function clearMembersTeamCache(): void
    {
        self::$resolvedMembersTeam = null;

        Cache::forget(self::MEMBERS_TEAM_CACHE_KEY);
        Cache::forget(self::MEMBERS_TEAM_ID_CACHE_KEY);
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
