<?php

namespace App\Http\Requests;

use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request für Kassenbuch-Einträge (Erstellung und Bearbeitung).
 */
class KassenbuchEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasAnyRole(Role::Kassenwart, Role::Vorstand, Role::Admin) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buchungsdatum' => 'required|date',
            'betrag' => 'required|numeric|not_in:0',
            'beschreibung' => 'required|string|max:255',
            'typ' => 'required|in:'.implode(',', KassenbuchEntryType::values()),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buchungsdatum.required' => 'Bitte gib ein Buchungsdatum an.',
            'buchungsdatum.date' => 'Das Buchungsdatum muss ein gültiges Datum sein.',
            'betrag.required' => 'Bitte gib einen Betrag an.',
            'betrag.numeric' => 'Der Betrag muss eine Zahl sein.',
            'betrag.not_in' => 'Der Betrag darf nicht 0 sein.',
            'beschreibung.required' => 'Bitte gib eine Beschreibung an.',
            'beschreibung.max' => 'Die Beschreibung darf maximal 255 Zeichen lang sein.',
            'typ.required' => 'Bitte wähle einen Buchungstyp aus.',
            'typ.in' => 'Der Buchungstyp ist ungültig.',
        ];
    }
}
