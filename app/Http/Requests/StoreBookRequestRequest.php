<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request für das Erstellen eines Buch-Gesuchs.
 */
class StoreBookRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);

        return [
            'series' => ['required', Rule::in($allowedTypes)],
            'book_number' => 'required|integer|min:1',
            'condition' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'series.required' => 'Bitte wähle eine Serie aus.',
            'series.in' => 'Die gewählte Serie ist ungültig.',
            'book_number.required' => 'Bitte wähle einen Roman aus.',
            'book_number.integer' => 'Die Roman-Nummer muss eine Zahl sein.',
            'book_number.min' => 'Die Roman-Nummer muss mindestens 1 sein.',
            'condition.required' => 'Bitte gib den gewünschten Zustand an.',
        ];
    }
}
