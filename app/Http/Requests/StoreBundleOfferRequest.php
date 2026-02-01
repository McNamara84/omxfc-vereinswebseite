<?php

namespace App\Http\Requests;

use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request für das Erstellen eines Stapel-Angebots (Bundle).
 */
class StoreBundleOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $photoRules = BookPhotoService::getValidationRules();

        return array_merge([
            'series' => ['required', Rule::in($allowedTypes)],
            'book_numbers' => 'required|string',
            'condition' => ['required', 'string', Rule::in(BundleService::CONDITION_ORDER)],
            'condition_max' => ['nullable', 'string', Rule::in(BundleService::CONDITION_ORDER)],
        ], $photoRules);
    }

    public function messages(): array
    {
        return [
            'series.required' => 'Bitte wähle eine Serie aus.',
            'series.in' => 'Die gewählte Serie ist ungültig.',
            'book_numbers.required' => 'Bitte gib die Roman-Nummern an.',
            'condition.required' => 'Bitte gib den Zustand an.',
            'condition.in' => 'Ungültiger Zustandswert.',
            'condition_max.in' => 'Ungültiger Zustandswert für "Bis".',
            'photos.max' => 'Maximal '.BookPhotoService::MAX_PHOTOS.' Fotos erlaubt.',
            'photos.*.max' => 'Jedes Foto darf maximal '.BookPhotoService::MAX_FILE_SIZE_KB.' KB groß sein.',
            'photos.*.mimes' => 'Erlaubte Formate: '.implode(', ', BookPhotoService::ALLOWED_EXTENSIONS),
        ];
    }
}
