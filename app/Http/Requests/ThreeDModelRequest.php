<?php

namespace App\Http\Requests;

use App\Services\ThreeDModelService;
use Illuminate\Foundation\Http\FormRequest;

class ThreeDModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization via Middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = ThreeDModelService::MAX_FILE_SIZE_KB;
        $maxThumbSize = ThreeDModelService::MAX_THUMBNAIL_SIZE_KB;
        $extensions = implode(',', ThreeDModelService::ALLOWED_EXTENSIONS);
        $thumbExtensions = implode(',', ThreeDModelService::ALLOWED_THUMBNAIL_EXTENSIONS);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'required_baxx' => ['required', 'integer', 'min:1', 'max:1000'],
            'maddraxikon_url' => ['nullable', 'url', 'max:500'],
            'thumbnail' => ['nullable', 'image', "mimes:{$thumbExtensions}", "max:{$maxThumbSize}"],
        ];

        // Bei Erstellung ist die 3D-Datei Pflicht, bei Update optional
        if ($this->isMethod('POST')) {
            $rules['model_file'] = ['required', 'file', "max:{$maxSize}"];
        } else {
            $rules['model_file'] = ['nullable', 'file', "max:{$maxSize}"];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Bitte gib einen Namen für das 3D-Modell ein.',
            'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
            'description.required' => 'Bitte gib eine Beschreibung ein.',
            'description.max' => 'Die Beschreibung darf maximal 2000 Zeichen lang sein.',
            'required_baxx.required' => 'Bitte gib die benötigten Baxx ein.',
            'required_baxx.min' => 'Der Baxx-Preis muss mindestens 1 sein.',
            'required_baxx.max' => 'Der Baxx-Preis darf maximal 1000 sein.',
            'model_file.required' => 'Bitte lade eine 3D-Datei hoch.',
            'model_file.max' => 'Die Datei darf maximal 100 MB groß sein.',
            'maddraxikon_url.url' => 'Bitte gib eine gültige URL ein.',
            'maddraxikon_url.max' => 'Die URL darf maximal 500 Zeichen lang sein.',
            'thumbnail.image' => 'Das Vorschaubild muss ein Bild sein.',
            'thumbnail.max' => 'Das Vorschaubild darf maximal 2 MB groß sein.',
        ];
    }
}
