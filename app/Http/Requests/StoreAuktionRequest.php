<?php

namespace App\Http\Requests;

use App\Enums\AuktionsStatus;
use App\Models\Auktion;
use App\Support\Euro;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreAuktionRequest extends FormRequest
{
    private const EURO_FORMAT_MESSAGE = 'Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.';

    public function authorize(): bool
    {
        return $this->user()?->can('manage', Auktion::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung_markdown' => ['nullable', 'string'],
            'startbetrag' => ['required', 'string', Euro::VALIDATION_RULE],
            'mindestschritt' => ['required', 'string', Euro::VALIDATION_RULE],
        ];
    }

    public function messages(): array
    {
        return [
            'startbetrag.regex' => self::EURO_FORMAT_MESSAGE,
            'mindestschritt.regex' => self::EURO_FORMAT_MESSAGE,
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateEuroAmount($validator, 'startbetrag', 0, true);
            $this->validateEuroAmount($validator, 'mindestschritt', 1, false);
        });
    }

    public function payload(): array
    {
        return [
            'titel' => $this->string('titel')->trim()->value(),
            'beschreibung_markdown' => $this->input('beschreibung_markdown'),
            'startbetrag_cent' => Euro::toCents((string) $this->input('startbetrag')),
            'mindestschritt_cent' => Euro::toCents((string) $this->input('mindestschritt')),
            'status' => AuktionsStatus::Laufend,
        ];
    }

    private function validateEuroAmount($validator, string $field, int $minimumInCents, bool $allowZero): void
    {
        if ($validator->errors()->has($field)) {
            return;
        }

        try {
            $amountInCents = Euro::toCents((string) $this->input($field));
        } catch (InvalidArgumentException) {
            $validator->errors()->add($field, self::EURO_FORMAT_MESSAGE);

            return;
        }

        if ($amountInCents < $minimumInCents || (! $allowZero && $amountInCents === 0)) {
            $validator->errors()->add($field, $field === 'startbetrag'
                ? 'Der Startbetrag muss mindestens 0,00 € sein.'
                : 'Der Mindestschritt muss größer als 0,00 € sein.');
        }
    }
}
