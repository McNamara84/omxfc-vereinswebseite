<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use App\Jobs\GeocodeUser;
use Illuminate\Support\Facades\Schema;
use App\Enums\Role;
use App\Models\Membership;

/**
 * @property-read Team|null $currentTeam
 * @property int $id
 * @property string $name
 * @property Carbon|null $bezahlt_bis
 * @property string|null $einstiegsroman
 * @property string|null $lesestand
 * @property string|null $lieblingsroman
 * @property string|null $lieblingsfigur
 * @property string|null $lieblingsmutation
 * @property string|null $lieblingsschauplatz
 * @property string|null $lieblingsautor
 * @property string|null $lieblingszyklus
 * @property string|null $lieblingsthema
 * @property string|null $lieblingshardcover
 * @property string|null $lieblingscover
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'vorname',
        'nachname',
        'strasse',
        'hausnummer',
        'plz',
        'stadt',
        'land',
        'lat',
        'lon',
        'telefon',
        'verein_gefunden',
        'mitgliedsbeitrag',
        'einstiegsroman',
        'lesestand',
        'lieblingsroman',
        'lieblingsfigur',
        'lieblingsmutation',
        'lieblingsschauplatz',
        'lieblingsautor',
        'lieblingszyklus',
        'lieblingsthema',
        'lieblingshardcover',
        'lieblingscover',
        'mitglied_seit',
        'bezahlt_bis',
        'notify_new_review',
        'last_activity',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mitglied_seit' => 'date',
            'bezahlt_bis' => 'date',
            'notify_new_review' => 'boolean',
            'last_activity' => 'integer',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    protected static function booted()
    {
        static::saved(function (User $user) {
            if (env('DISABLE_GEOCODING', false)) {
                return;
            }

            if (! Schema::hasColumns('users', ['lat', 'lon'])) {
                return;
            }

            if (($user->wasRecentlyCreated || $user->wasChanged('plz') || $user->wasChanged('land'))
                && (is_null($user->lat) || is_null($user->lon))) {
                GeocodeUser::dispatch($user);
            }
        });
    }

    /**
     * Get the todos created by the user.
     */
    public function createdTodos(): HasMany
    {
        return $this->hasMany(Todo::class, 'created_by');
    }

    /**
     * Get the todos assigned to the user.
     */
    public function assignedTodos(): HasMany
    {
        return $this->hasMany(Todo::class, 'assigned_to');
    }

    /**
     * Get the todos verified by the user.
     */
    public function verifiedTodos(): HasMany
    {
        return $this->hasMany(Todo::class, 'verified_by');
    }

    /**
     * Get the user's points.
     */
    public function points(): HasMany
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Get the total points for a specific team.
     */
    public function totalPointsForTeam(Team $team): int
    {
        return $this->points()
            ->where('team_id', $team->id)
            ->sum('points');
    }

    /**
     * Increment points for the current team.
     */
    public function incrementTeamPoints(int $points = 1): void
    {
        if ($this->currentTeam) {
            UserPoint::create([
                'user_id' => $this->id,
                'team_id' => $this->currentTeam->id,
                'points' => $points
            ]);
        }
    }

    /**
     * Get the role of the user on the current team.
     *
     * @return string|null
     */
    public function role(): ?Role
    {
        if (! $this->currentTeam) {
            return null;
        }

        $membership = Membership::where('team_id', $this->currentTeam->id)
            ->where('user_id', $this->id)
            ->first();

        return Role::tryFrom($membership->role ?? null);
    }

    /**
     * Check if the user has the given role on the current team.
     *
     * @param  Role  $role
     * @return bool
     */
    public function hasRole(Role $role): bool
    {
        return $this->role() === $role;
    }

    /**
     * Check if the user has any of the given roles on the current team.
     */
    public function hasAnyRole(Role ...$roles): bool
    {
        $userRole = $this->role();

        return $userRole && in_array($userRole, $roles, true);
    }

    /**
     * Determine if the user holds a Vorstand-level role.
     */
    public function hasVorstandRole(): bool
    {
        return $this->hasAnyRole(Role::Admin, Role::Vorstand, Role::Kassenwart);
    }

    /**
     * Check if the user is a member of the given team.
     */
    public function isMemberOfTeam(string $teamName): bool
    {
        return $this->teams()->where('name', $teamName)->exists();
    }

    /**
     * Check if the user owns the given team.
     */
    public function isOwnerOfTeam(string $teamName): bool
    {
        return $this->ownedTeams()->where('name', $teamName)->exists();
    }
}
