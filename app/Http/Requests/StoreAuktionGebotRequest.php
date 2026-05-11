<?php

namespace App\Http\Requests;

use App\Models\Auktion;
use App\Support\Euro;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreAuktionGebotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $auktion = $this->route('auktion');

        return $auktion && ($this->user()?->can('bid', $auktion) ?? false);
    }

    public function rules(): array
    {
        return [
            'betrag' => ['required', 'string', Euro::VALIDATION_RULE],
        ];
    }

    public function messages(): array
    {
        return [
            'betrag.regex' => 'Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.',
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

            try {
                $betragInCent = Euro::toCents((string) $this->input('betrag'));
            } catch (InvalidArgumentException) {
                $validator->errors()->add('betrag', 'Bitte gib ein gültiges Gebot in Euro ein.');

                return;
            }

            if ($betragInCent < $auktion->naechstesMindestgebotCent()) {
                $validator->errors()->add('betrag', 'Das Gebot muss mindestens '.$auktion->naechstesMindestgebot().' betragen.');
            }
        });
    }

    public function betragInCent(): int
    {
        return Euro::toCents((string) $this->input('betrag'));
    }
}
