<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'vorname' => ['required', 'string', 'max:255'],
            'nachname' => ['required', 'string', 'max:255'],
            'strasse' => ['required', 'string', 'max:255'],
            'hausnummer' => ['required', 'string', 'max:10'],
            'plz' => ['required', 'string', 'max:10'],
            'stadt' => ['required', 'string', 'max:255'],
            'land' => ['required', Rule::in(['Deutschland', 'Österreich', 'Schweiz'])],
            'telefon' => ['nullable', 'string', 'max:20'],
            'mitgliedsbeitrag' => ['required', 'numeric', 'min:12', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:4096'],
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
            'telefon' => $input['telefon'],
            'mitgliedsbeitrag' => $input['mitgliedsbeitrag'],
            'email' => $input['email'],
        ];

        // Prüfen, ob die Mail geändert wurde und Nutzer verifiziert werden muss
        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $updates);
        } else {
            $user->forceFill($updates)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill(array_merge($input, [
            'email_verified_at' => null,
        ]))->save();

        $user->sendEmailVerificationNotification();
    }
}
