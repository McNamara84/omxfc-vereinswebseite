<?php

namespace App\Models;

use App\Enums\Role;
use App\Jobs\GeocodeUser;
use App\Services\RewardService;
use Carbon\Carbon;
use Database\Factories\UserFactory;
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

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * @var array<string, bool>
     */
    private static array $geocodeColumnsAvailable = [];

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
        'alias',
        'author_aliases',
        'contact_release_email',
        'contact_release_phone',
        'contact_release_maddraxikon',
        'contact_release_nextcloud',
        'maddraxikon_username',
        'nextcloud_username',
        'contact_released_at',
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
            'author_aliases' => 'array',
            'contact_release_email' => 'boolean',
            'contact_release_phone' => 'boolean',
            'contact_release_maddraxikon' => 'boolean',
            'contact_release_nextcloud' => 'boolean',
            'contact_released_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::saved(function (User $user) {
            if (! $user->hasGeocodeColumns()) {
                return;
            }

            if (($user->wasRecentlyCreated || $user->wasChanged('plz') || $user->wasChanged('land'))
                && (is_null($user->lat) || is_null($user->lon))) {
                GeocodeUser::dispatch($user);
            }
        });
    }

    private function hasGeocodeColumns(): bool
    {
        $connectionName = $this->getConnectionName() ?? config('database.default', 'default');
        $cacheKey = implode(':', [
            $connectionName,
            (string) config("database.connections.{$connectionName}.database", 'default'),
            $this->getTable(),
        ]);

        return self::$geocodeColumnsAvailable[$cacheKey]
            ??= Schema::connection($connectionName)->hasColumns($this->getTable(), ['lat', 'lon']);
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
     * Get the user's saved RPG characters.
     */
    public function rpgCharacters(): HasMany
    {
        return $this->hasMany(RpgCharacter::class);
    }

    /**
     * Get the tour assignments for the user.
     */
    public function tourAssignments(): HasMany
    {
        return $this->hasMany(TourAssignment::class);
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

        $membership = Membership::query()->where('team_id', $this->currentTeam->id)
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
     * Get the role of the user in the Mitglieder-Team.
     */
    public function mitgliederTeamRole(): ?Role
    {
        $mitgliederTeam = Team::membersTeam();

        if (! $mitgliederTeam) {
            return null;
        }

        $membership = Membership::query()->where('team_id', $mitgliederTeam->id)
            ->where('user_id', $this->id)
            ->first();

        return Role::tryFrom($membership->role ?? null);
    }

    /**
     * Check if the user has any of the given roles in the Mitglieder-Team.
     */
    public function hasAnyMitgliederTeamRole(Role ...$roles): bool
    {
        $userRole = $this->mitgliederTeamRole();

        return $userRole instanceof Role && in_array($userRole, $roles, true);
    }

    /**
     * Determine if the user may manage club events regardless of the active team.
     */
    public function canManageVeranstaltungen(): bool
    {
        return $this->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand, Role::Kassenwart);
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
     * Get the preferred first name for public AG listings.
     */
    public function publicFirstName(): ?string
    {
        if (filled($this->vorname)) {
            return trim((string) $this->vorname);
        }

        $fullName = trim((string) $this->name);

        if ($fullName === '') {
            return null;
        }

        $nameParts = preg_split('/\s+/u', $fullName);

        if (! is_array($nameParts) || $nameParts === []) {
            return null;
        }

        return $nameParts[0] ?: null;
    }

    public function displayAlias(): ?string
    {
        $alias = trim((string) $this->alias);

        return $alias === '' ? null : $alias;
    }

    public function nicknameOrName(): string
    {
        return $this->displayAlias() ?? trim((string) $this->name);
    }

    /**
     * @return array<int, string>
     */
    public function displayAliases(): array
    {
        $aliases = [];
        $displayAlias = $this->displayAlias();

        if ($displayAlias) {
            $aliases[] = $displayAlias;
        }

        foreach ($this->author_aliases ?? [] as $authorAlias) {
            $authorAlias = trim((string) $authorAlias);

            if ($authorAlias !== '') {
                $aliases[] = $authorAlias;
            }
        }

        return array_values(array_unique($aliases));
    }

    public function hasReleasedContactMethods(): bool
    {
        return $this->releasedContactMethods() !== [];
    }

    /**
     * @return array<int, array{key:string,label:string,value:string,href:string,icon:string}>
     */
    public function releasedContactMethods(): array
    {
        $methods = [];

        if ($this->contact_release_email && filled($this->email)) {
            $methods[] = [
                'key' => 'email',
                'label' => 'E-Mail',
                'value' => (string) $this->email,
                'href' => 'mailto:'.$this->email,
                'icon' => 'o-envelope',
            ];
        }

        if ($this->contact_release_phone && filled($this->telefon)) {
            $phoneHref = preg_replace('/[^\d+]/', '', (string) $this->telefon) ?: (string) $this->telefon;

            $methods[] = [
                'key' => 'phone',
                'label' => 'Telefon',
                'value' => (string) $this->telefon,
                'href' => 'tel:'.$phoneHref,
                'icon' => 'o-phone',
            ];
        }

        $maddraxikonProfileUrl = $this->maddraxikonProfileUrl();

        if ($this->contact_release_maddraxikon && filled($this->maddraxikon_username) && $maddraxikonProfileUrl) {
            $methods[] = [
                'key' => 'maddraxikon',
                'label' => 'Maddraxikon',
                'value' => (string) $this->maddraxikon_username,
                'href' => $maddraxikonProfileUrl,
                'icon' => 'o-book-open',
            ];
        }

        $nextcloudProfileUrl = $this->nextcloudProfileUrl();

        if ($this->contact_release_nextcloud && filled($this->nextcloud_username) && $nextcloudProfileUrl) {
            $methods[] = [
                'key' => 'nextcloud',
                'label' => 'Nextcloud',
                'value' => (string) $this->nextcloud_username,
                'href' => $nextcloudProfileUrl,
                'icon' => 'o-cloud',
            ];
        }

        return $methods;
    }

    public function maddraxikonProfileUrl(): ?string
    {
        $username = trim((string) $this->maddraxikon_username);

        if ($username === '') {
            return null;
        }

        $username = preg_replace('/\s+/u', '_', $username) ?: $username;

        return 'https://de.maddraxikon.com/index.php?title=Benutzer:'.rawurlencode($username);
    }

    public function nextcloudProfileUrl(): ?string
    {
        $username = trim((string) $this->nextcloud_username);

        if ($username === '') {
            return null;
        }

        return 'https://cloud.maddrax-fanclub.de/u/'.rawurlencode($username);
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
        return app(RewardService::class)->getSpentBaxx($this);
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
