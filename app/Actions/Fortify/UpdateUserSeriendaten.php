<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserSeriendaten
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'einstiegsroman' => ['nullable', 'string', 'max:255'],
            'lesestand' => ['nullable', 'string', 'max:255'],
            'lieblingsroman' => ['nullable', 'string', 'max:255'],
            'lieblingsfigur' => ['nullable', 'string', 'max:255'],
            'lieblingsmutation' => ['nullable', 'string', 'max:255'],
            'lieblingsschauplatz' => ['nullable', 'string', 'max:255'],
            'lieblingsautor' => ['nullable', 'string', 'max:255'],
            'lieblingszyklus' => ['nullable', 'string', 'max:255'],
            'lieblingsthema' => ['nullable', 'string', 'max:255'],
        ])->validateWithBag('updateSeriendaten');

        $user->forceFill([
            'einstiegsroman' => $input['einstiegsroman'] ?? null,
            'lesestand' => $input['lesestand'] ?? null,
            'lieblingsroman' => $input['lieblingsroman'] ?? null,
            'lieblingsfigur' => $input['lieblingsfigur'] ?? null,
            'lieblingsmutation' => $input['lieblingsmutation'] ?? null,
            'lieblingsschauplatz' => $input['lieblingsschauplatz'] ?? null,
            'lieblingsautor' => $input['lieblingsautor'] ?? null,
            'lieblingszyklus' => $input['lieblingszyklus'] ?? null,
            'lieblingsthema' => $input['lieblingsthema'] ?? null,
        ])->save();
    }
}
