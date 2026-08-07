<?php

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use App\Services\ProfileContactUpdateNotifier;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    private const MEMBER_MAP_CACHE_FIELDS = [
        'alias',
        'vorname',
        'nachname',
        'plz',
        'stadt',
        'land',
    ];

    public function __construct(
        private readonly MemberMapCacheService $memberMapCacheService,
        private readonly ProfileContactUpdateNotifier $profileContactUpdateNotifier,
    ) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $input = $this->normalizeNullableStringInputs($input, [
            'telefon',
            'nextcloud_username',
        ]);
        $hasActiveMaddraxikonLink = $user->maddraxikonAccountLink()->active()->exists();
        $wantsMaddraxikonRelease = $this->booleanInput($input['contact_release_maddraxikon'] ?? false);

        Validator::make($input, [
            'vorname' => ['required', 'string', 'max:255'],
            'nachname' => ['required', 'string', 'max:255'],
            'strasse' => ['required', 'string', 'max:255'],
            'hausnummer' => ['required', 'string', 'max:10'],
            'plz' => ['required', 'string', 'max:10'],
            'stadt' => ['required', 'string', 'max:255'],
            'land' => ['required', Rule::in(['Deutschland', 'Österreich', 'Schweiz'])],
            'telefon' => [
                Rule::requiredIf(fn () => $this->booleanInput($input['contact_release_phone'] ?? false)),
                'nullable',
                'string',
                'max:20',
            ],
            'mitgliedsbeitrag' => ['required', 'numeric', 'min:12', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'alias' => ['nullable', 'string', 'max:255'],
            'author_aliases' => ['nullable', 'array', 'max:10'],
            'author_aliases.*' => ['nullable', 'string', 'max:255'],
            'contact_release_email' => ['nullable', 'boolean'],
            'contact_release_phone' => ['nullable', 'boolean'],
            'contact_release_maddraxikon' => [
                'nullable',
                'boolean',
                Rule::prohibitedIf($wantsMaddraxikonRelease && ! $hasActiveMaddraxikonLink),
            ],
            'contact_release_nextcloud' => ['nullable', 'boolean'],
            'nextcloud_username' => [
                Rule::requiredIf(fn () => $this->booleanInput($input['contact_release_nextcloud'] ?? false)),
                'nullable',
                'string',
                'max:255',
            ],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,gif,webp', 'max:8192'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $baseUpdates = [
            'vorname' => $input['vorname'],
            'nachname' => $input['nachname'],
            'strasse' => $input['strasse'],
            'hausnummer' => $input['hausnummer'],
            'plz' => $input['plz'],
            'stadt' => $input['stadt'],
            'land' => $input['land'],
            'telefon' => $this->nullableString($input['telefon'] ?? null),
            'mitgliedsbeitrag' => $input['mitgliedsbeitrag'],
            'email' => $input['email'],
            'alias' => $this->nullableString($input['alias'] ?? null),
            'author_aliases' => $user->hasRole(Role::Ehrenmitglied)
                ? $this->cleanAuthorAliases($input['author_aliases'] ?? [])
                : [],
            'contact_release_email' => $this->booleanInput($input['contact_release_email'] ?? false),
            'contact_release_phone' => $this->booleanInput($input['contact_release_phone'] ?? false),
            'contact_release_maddraxikon' => $wantsMaddraxikonRelease,
            'contact_release_nextcloud' => $this->booleanInput($input['contact_release_nextcloud'] ?? false),
            'nextcloud_username' => $this->nullableString($input['nextcloud_username'] ?? null),
        ];

        $result = DB::transaction(function () use ($user, $baseUpdates): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $contactSnapshotBefore = $this->contactSnapshot($lockedUser);
            $updates = $baseUpdates;

            $activeLink = $lockedUser->maddraxikonAccountLink()
                ->active()
                ->lockForUpdate()
                ->first();

            if (! $activeLink) {
                $updates['contact_release_maddraxikon'] = false;
            }

            $changedContactLabels = $this->changedContactLabels($contactSnapshotBefore, [
                'email' => (string) $updates['email'],
                'telefon' => $updates['telefon'],
                'contact_release_email' => $updates['contact_release_email'],
                'contact_release_phone' => $updates['contact_release_phone'],
                'contact_release_maddraxikon' => $updates['contact_release_maddraxikon'],
                'contact_release_nextcloud' => $updates['contact_release_nextcloud'],
                'nextcloud_username' => $updates['nextcloud_username'],
            ]);

            $contactChangedAt = null;

            if ($changedContactLabels !== []) {
                $contactChangedAt = now();
                $updates['contact_released_at'] = $contactChangedAt;
            }

            $requiresEmailVerification = $updates['email'] !== $lockedUser->email
                && $lockedUser instanceof MustVerifyEmail;

            if ($requiresEmailVerification) {
                $updates['email_verified_at'] = null;
            }

            $lockedUser->forceFill($updates)->save();

            return [
                'changed_contact_labels' => $changedContactLabels,
                'contact_changed_at' => $contactChangedAt,
                'map_cache_changed' => $lockedUser->wasChanged(self::MEMBER_MAP_CACHE_FIELDS),
                'requires_email_verification' => $requiresEmailVerification,
            ];
        }, attempts: 3);

        $user->refresh();

        if ($result['requires_email_verification']) {
            $user->sendEmailVerificationNotification();
        }

        if ($result['map_cache_changed'] && ($membersTeam = Team::membersTeam())) {
            $this->memberMapCacheService->invalidate($membersTeam);
        }

        if ($result['changed_contact_labels'] !== []) {
            $this->profileContactUpdateNotifier->notify(
                $user,
                $result['changed_contact_labels'],
                $result['contact_changed_at'],
            );
        }
    }

    private function booleanInput(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function normalizeNullableStringInputs(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $input[$key] = $this->nullableString($input[$key]);
            }
        }

        return $input;
    }

    /**
     * @return array<int, string>
     */
    private function cleanAuthorAliases(mixed $aliases): array
    {
        if (! is_array($aliases)) {
            return [];
        }

        $cleaned = [];

        foreach ($aliases as $alias) {
            $alias = $this->nullableString($alias);

            if ($alias !== null) {
                $cleaned[] = $alias;
            }
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * @return array<string, mixed>
     */
    private function contactSnapshot(User $user): array
    {
        return [
            'email' => (string) $user->email,
            'telefon' => $this->nullableString($user->telefon),
            'contact_release_email' => (bool) $user->contact_release_email,
            'contact_release_phone' => (bool) $user->contact_release_phone,
            'contact_release_maddraxikon' => (bool) $user->contact_release_maddraxikon,
            'contact_release_nextcloud' => (bool) $user->contact_release_nextcloud,
            'nextcloud_username' => $this->nullableString($user->nextcloud_username),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function changedContactLabels(array $before, array $after): array
    {
        $changed = [];

        if ($before['contact_release_email'] !== $after['contact_release_email']
            || (($before['contact_release_email'] || $after['contact_release_email']) && $before['email'] !== $after['email'])) {
            $changed[] = 'E-Mail';
        }

        if ($before['contact_release_phone'] !== $after['contact_release_phone']
            || (($before['contact_release_phone'] || $after['contact_release_phone']) && $before['telefon'] !== $after['telefon'])) {
            $changed[] = 'Telefon';
        }

        if ($before['contact_release_maddraxikon'] !== $after['contact_release_maddraxikon']) {
            $changed[] = 'Maddraxikon';
        }

        if ($before['contact_release_nextcloud'] !== $after['contact_release_nextcloud']
            || (($before['contact_release_nextcloud'] || $after['contact_release_nextcloud']) && $before['nextcloud_username'] !== $after['nextcloud_username'])) {
            $changed[] = 'Nextcloud';
        }

        return array_values(array_unique($changed));
    }
}
