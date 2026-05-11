<?php

namespace App\Http\Requests;

use App\Models\Auktion;
use App\Support\Euro;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class UpdateAuktionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $auktion = $this->route('auktion');

        return $auktion && ($this->user()?->can('update', $auktion) ?? false);
    }

    public function rules(): array
    {
        $auktion = $this->route('auktion');
        $baseRule = $auktion && $auktion->hasGebote()
            ? ['nullable', 'string', Euro::VALIDATION_RULE]
            : ['required', 'string', Euro::VALIDATION_RULE];

        return [
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung_markdown' => ['nullable', 'string'],
            'startbetrag' => $baseRule,
            'mindestschritt' => $baseRule,
        ];
    }

    public function messages(): array
    {
        return [
            'startbetrag.regex' => 'Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.',
            'mindestschritt.regex' => 'Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Auktion|null $auktion */
            $auktion = $this->route('auktion');

            if (! $auktion) {
                return;
            }

            if ($auktion->hasGebote()) {
                $this->validateLockedMoneyField($validator, $auktion, 'startbetrag', $auktion->startbetrag_cent, 'Der Startbetrag kann nach dem ersten Gebot nicht mehr geändert werden.');
                $this->validateLockedMoneyField($validator, $auktion, 'mindestschritt', $auktion->mindestschritt_cent, 'Der Mindestschritt kann nach dem ersten Gebot nicht mehr geändert werden.');

                return;
            }

            $this->validateEditableMoneyField($validator, 'startbetrag', 0, true);
            $this->validateEditableMoneyField($validator, 'mindestschritt', 1, false);
        });
    }

    public function payload(Auktion $auktion): array
    {
        $payload = [
            'titel' => $this->string('titel')->trim()->value(),
            'beschreibung_markdown' => $this->input('beschreibung_markdown'),
        ];

        if ($auktion->hasGebote()) {
            return $payload;
        }

        $payload['startbetrag_cent'] = Euro::toCents((string) $this->input('startbetrag'));
        $payload['mindestschritt_cent'] = Euro::toCents((string) $this->input('mindestschritt'));

        return $payload;
    }

    private function validateLockedMoneyField($validator, Auktion $auktion, string $field, int $storedValue, string $message): void
    {
        if (! $this->filled($field)) {
            return;
        }

        try {
            $incomingValue = Euro::toCents((string) $this->input($field));
        } catch (InvalidArgumentException) {
            $validator->errors()->add($field, 'Bitte gib einen gültigen Euro-Betrag ein.');

            return;
        }

        if ($incomingValue !== $storedValue) {
            $validator->errors()->add($field, $message);
        }
    }

    private function validateEditableMoneyField($validator, string $field, int $minimumInCents, bool $allowZero): void
    {
        try {
            $amountInCents = Euro::toCents((string) $this->input($field));
        } catch (InvalidArgumentException) {
            $validator->errors()->add($field, 'Bitte gib einen gültigen Euro-Betrag ein.');

            return;
        }

        if ($amountInCents < $minimumInCents || (! $allowZero && $amountInCents === 0)) {
            $validator->errors()->add($field, $field === 'startbetrag'
                ? 'Der Startbetrag darf nicht negativ sein.'
                : 'Der Mindestschritt muss größer als 0,00 € sein.');
        }
    }
}
