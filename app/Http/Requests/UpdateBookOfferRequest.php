<?php

namespace App\Http\Requests;

use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request für das Aktualisieren eines einzelnen Buch-Angebots.
 */
class UpdateBookOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer && $this->user()->can('update', $offer);
    }

    public function rules(): array
    {
        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
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
     * Zusätzliche Validierung nach den Standardregeln.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $offer = $this->route('offer');

            // Prüfe ob Angebot in einer Tauschaktion ist
            if ($offer && ($offer->completed || $offer->swap)) {
                $validator->errors()->add(
                    'offer',
                    'Angebote in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.'
                );
            }

            // Validiere Foto-Anzahl
            $this->validatePhotoCount($validator, $offer);
        });
    }

    /**
     * Validiert die Gesamtanzahl der Fotos.
     */
    private function validatePhotoCount($validator, ?BookOffer $offer): void
    {
        if (! $offer) {
            return;
        }

        $removePhotos = collect($this->input('remove_photos', []));
        $existingPhotos = collect($offer->photos ?? []);
        $remainingCount = $existingPhotos->reject(fn ($path) => $removePhotos->contains($path))->count();
        $newCount = collect($this->file('photos', []))->filter()->count();

        if ($remainingCount + $newCount > BookPhotoService::MAX_PHOTOS) {
            $validator->errors()->add(
                'photos',
                'Du kannst maximal '.BookPhotoService::MAX_PHOTOS.' Fotos für ein Angebot speichern.'
            );
        }
    }
}
