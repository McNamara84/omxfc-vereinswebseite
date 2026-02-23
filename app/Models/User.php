<?php

namespace App\Models;

use App\Enums\Role;
use App\Jobs\GeocodeUser;
use App\Services\RewardService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

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
                'points' => $points,
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

    /**
     * Get all reward purchases for this user.
     */
    public function rewardPurchases(): HasMany
    {
        return $this->hasMany(RewardPurchase::class);
    }

    /**
     * Get only active (non-refunded) reward purchases.
     */
    public function activeRewardPurchases(): HasMany
    {
        return $this->hasMany(RewardPurchase::class)->whereNull('refunded_at');
    }

    /**
     * Check if the user has unlocked a specific reward by slug.
     */
    public function hasUnlockedReward(string $slug): bool
    {
        return app(RewardService::class)->hasUnlockedReward($this, $slug);
    }

    /**
     * Get total Baxx spent on active (non-refunded) purchases.
     */
    public function getSpentBaxx(): int
    {
        return (int) $this->activeRewardPurchases()->sum('cost_baxx');
    }

    /**
     * Get the available (spendable) Baxx for the user.
     * Available = Earned - Spent on active purchases.
     */
    public function getAvailableBaxx(): int
    {
        return app(RewardService::class)->getAvailableBaxx($this);
    }
}
