<?php

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Mail\ProfileContactUpdated;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private readonly MemberMapCacheService $memberMapCacheService,
    ) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $contactSnapshotBefore = $this->contactSnapshot($user);
        $input = $this->normalizeNullableStringInputs($input, [
            'telefon',
            'maddraxikon_username',
            'nextcloud_username',
        ]);

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
            'contact_release_maddraxikon' => ['nullable', 'boolean'],
            'contact_release_nextcloud' => ['nullable', 'boolean'],
            'maddraxikon_username' => [
                Rule::requiredIf(fn () => $this->booleanInput($input['contact_release_maddraxikon'] ?? false)),
                'nullable',
                'string',
                'max:255',
            ],
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

        $updates = [
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
            'contact_release_maddraxikon' => $this->booleanInput($input['contact_release_maddraxikon'] ?? false),
            'contact_release_nextcloud' => $this->booleanInput($input['contact_release_nextcloud'] ?? false),
            'maddraxikon_username' => $this->nullableString($input['maddraxikon_username'] ?? null),
            'nextcloud_username' => $this->nullableString($input['nextcloud_username'] ?? null),
        ];

        $changedContactLabels = $this->changedContactLabels($contactSnapshotBefore, [
            'email' => (string) $updates['email'],
            'telefon' => $updates['telefon'],
            'contact_release_email' => $updates['contact_release_email'],
            'contact_release_phone' => $updates['contact_release_phone'],
            'contact_release_maddraxikon' => $updates['contact_release_maddraxikon'],
            'contact_release_nextcloud' => $updates['contact_release_nextcloud'],
            'maddraxikon_username' => $updates['maddraxikon_username'],
            'nextcloud_username' => $updates['nextcloud_username'],
        ]);

        $contactChangedAt = null;

        if ($changedContactLabels !== []) {
            $contactChangedAt = now();
            $updates['contact_released_at'] = $contactChangedAt;
        }

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $updates);
        } else {
            $user->forceFill($updates)->save();
        }

        if ($user->wasChanged('alias') && ($membersTeam = Team::membersTeam())) {
            $this->memberMapCacheService->invalidate($membersTeam);
        }

        if ($changedContactLabels !== []) {
            $this->notifyBoardAboutContactUpdate($user->refresh(), $changedContactLabels, $contactChangedAt);
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill(array_merge($input, [
            'email_verified_at' => null,
        ]))->save();

        $user->sendEmailVerificationNotification();
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
            'maddraxikon_username' => $this->nullableString($user->maddraxikon_username),
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

        if ($before['contact_release_maddraxikon'] !== $after['contact_release_maddraxikon']
            || (($before['contact_release_maddraxikon'] || $after['contact_release_maddraxikon']) && $before['maddraxikon_username'] !== $after['maddraxikon_username'])) {
            $changed[] = 'Maddraxikon';
        }

        if ($before['contact_release_nextcloud'] !== $after['contact_release_nextcloud']
            || (($before['contact_release_nextcloud'] || $after['contact_release_nextcloud']) && $before['nextcloud_username'] !== $after['nextcloud_username'])) {
            $changed[] = 'Nextcloud';
        }

        return array_values(array_unique($changed));
    }

    /**
     * @param  array<int, string>  $changedContactLabels
     */
    private function notifyBoardAboutContactUpdate(User $user, array $changedContactLabels, CarbonInterface $contactChangedAt): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            Log::warning('Profil-Kontaktaktualisierung ohne Mitglieder-Team.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $recipients = $team->activeUsers()
            ->wherePivotIn('role', [
                Role::Admin->value,
                Role::Vorstand->value,
                Role::Kassenwart->value,
            ])
            ->whereNotNull('users.email')
            ->pluck('users.email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            Log::warning('Profil-Kontaktaktualisierung ohne Vorstand-Empfaenger.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        Mail::to($recipients)->queue(new ProfileContactUpdated($user, $changedContactLabels, $contactChangedAt));
    }
}
