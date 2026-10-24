<?php

namespace App\Support\Romantausch;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InputRules
{
    public const TYPES = [BookType::MaddraxDieDunkleZukunftDerErde, BookType::MaddraxHardcover, BookType::MissionMars, BookType::DasVolkDerTiefe, BookType::ZweiTausendZwölfDasJahrDerApokalypse, BookType::DieAbenteurer];

    public static function single(bool $photos = false): array
    {
        return [
            'series' => ['required', Rule::in(array_column(self::TYPES, 'value'))],
            'book_number' => 'required|integer|min:1',
            'condition' => 'required|string',
        ] + ($photos ? BookPhotoService::getValidationRules() : []);
    }

    public static function bundle(bool $editing = false): array
    {
        return ($editing ? [] : ['series' => self::single()['series']]) + [
            'book_numbers' => 'required|string',
            'condition' => ['required', 'string', Rule::in(BundleService::CONDITION_ORDER)],
            'condition_max' => ['nullable', 'string', Rule::in(BundleService::CONDITION_ORDER)],
        ] + BookPhotoService::getValidationRules();
    }

    public static function messages(bool $request = false): array
    {
        return [
            'series.required' => 'Bitte wähle eine Serie aus.',
            'series.in' => 'Die gewählte Serie ist ungültig.',
            'book_number.required' => 'Bitte wähle einen Roman aus.',
            'book_number.integer' => 'Die Roman-Nummer muss eine Zahl sein.',
            'book_number.min' => 'Die Roman-Nummer muss mindestens 1 sein.',
            'book_numbers.required' => 'Bitte gib die Roman-Nummern an.',
            'condition.required' => $request ? 'Bitte gib den gewünschten Zustand an.' : 'Bitte gib den Zustand an.',
            'condition.in' => 'Ungültiger Zustandswert.',
            'condition_max.in' => 'Ungültiger Zustandswert für "Bis".',
            'photos.max' => 'Maximal '.BookPhotoService::MAX_PHOTOS.' Fotos erlaubt.',
            'photos.*.max' => 'Jedes Foto darf maximal '.BookPhotoService::MAX_FILE_SIZE_KB.' KB groß sein.',
            'photos.*.mimes' => 'Erlaubte Formate: '.implode(', ', BookPhotoService::ALLOWED_EXTENSIONS),
        ];
    }

    public static function validate(array $data, array $rules, bool $request = false): array
    {
        $data = array_map(static fn ($value) => $value === '' ? null : $value, $data);

        return Validator::make($data, $rules, self::messages($request))->validate();
    }

    public static function book(array $data): Book
    {
        return Book::where('type', $data['series'])->where('roman_number', $data['book_number'])->first()
            ?? throw ValidationException::withMessages(['book_number' => 'Ausgewählter Roman nicht gefunden.']);
    }

    public static function editable(bool $blocked, string $field, string $noun): void
    {
        if ($blocked) {
            throw ValidationException::withMessages([$field => $noun.' in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.']);
        }
    }
}
