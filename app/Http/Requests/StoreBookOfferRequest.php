<?php

namespace App\Http\Requests;

use App\Enums\BookType;
use App\Services\Romantausch\BookPhotoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request für das Erstellen eines einzelnen Buch-Angebots.
 */
class StoreBookOfferRequest extends FormRequest
{
    /**
     * Erlaubte Buchtypen für die Romantauschbörse.
     */
    public const ALLOWED_TYPES = [
        BookType::MaddraxDieDunkleZukunftDerErde,
        BookType::MaddraxHardcover,
        BookType::MissionMars,
        BookType::DasVolkDerTiefe,
        BookType::ZweiTausendZwölfDasJahrDerApokalypse,
        BookType::DieAbenteurer,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedTypes = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $photoRules = BookPhotoService::getValidationRules();

        return array_merge([
            'series' => ['required', Rule::in($allowedTypes)],
            'book_number' => 'required|integer|min:1',
            'condition' => 'required|string',
        ], $photoRules);
    }

    public function messages(): array
    {
        return [
            'series.required' => 'Bitte wähle eine Serie aus.',
            'series.in' => 'Die gewählte Serie ist ungültig.',
            'book_number.required' => 'Bitte wähle einen Roman aus.',
            'book_number.integer' => 'Die Roman-Nummer muss eine Zahl sein.',
            'book_number.min' => 'Die Roman-Nummer muss mindestens 1 sein.',
            'condition.required' => 'Bitte gib den Zustand an.',
            'photos.max' => 'Maximal '.BookPhotoService::MAX_PHOTOS.' Fotos erlaubt.',
            'photos.*.max' => 'Jedes Foto darf maximal '.BookPhotoService::MAX_FILE_SIZE_KB.' KB groß sein.',
            'photos.*.mimes' => 'Erlaubte Formate: '.implode(', ', BookPhotoService::ALLOWED_EXTENSIONS),
        ];
    }

    /**
     * Gibt die erlaubten Buchtypen zurück.
     *
     * @return array<BookType>
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }
}
