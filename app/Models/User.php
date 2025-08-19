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
        ];
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
    public function role(): ?string
    {
        if (!$this->currentTeam) {
            return null;
        }

        $membership = $this->teams()
            ->wherePivot('team_id', $this->currentTeam->id)
            ->first();

        return $membership->membership->role ?? null;
    }

    /**
     * Check if the user has the given role on the current team.
     *
     * @param  string  $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role() === $role;
    }

    /**
     * Determine if the user holds a Vorstand-level role.
     */
    public function hasVorstandRole(): bool
    {
        return $this->hasRole('Admin')
            || $this->hasRole('Vorstand')
            || $this->hasRole('Kassenwart');
    }
}
