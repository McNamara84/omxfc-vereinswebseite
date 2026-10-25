<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateUserSeriendaten
{
    public function __construct(private readonly SeriesProfileOptions $options) {}

    public function update(User $user, array $input): void
    {
        $options = $this->options->all();
        DB::transaction(function () use ($user, $input, $options) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $values = [];
            $rules = [];
            foreach (SeriesProfileCompletion::FIELDS as $field => $label) {
                if (! array_key_exists($field, $input)) {
                    continue;
                }
                $values[$field] = SeriesProfileCompletion::normalize($input[$field]);
                $rules[$field] = ['nullable', 'string', 'max:255'];
                if (array_key_exists($field, $options)) {
                    $allowed = $options[$field];
                    $existing = SeriesProfileCompletion::normalize($lockedUser->getAttribute($field));
                    if ($existing !== null) {
                        $allowed[] = $existing;
                    }
                    $rules[$field][] = Rule::in($allowed);
                }
            }

            Validator::make($values, $rules, [], SeriesProfileCompletion::FIELDS)->validateWithBag('updateSeriendaten');
            $lockedUser->forceFill($values)->save();
        });
        $user->refresh();
    }
}
