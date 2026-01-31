<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request für Fanfiction-Erstellung und -Bearbeitung.
 */
class FanfictionRequest extends FormRequest
{
    /**
     * Maximale Anzahl von Fotos pro Fanfiction.
     */
    private const MAX_PHOTOS = 5;

    /**
     * Maximale Dateigröße pro Foto in KB.
     */
    private const MAX_PHOTO_SIZE_KB = 2048;

    /**
     * Erlaubte Dateiendungen für Fotos.
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * Prüft die Rolle explizit im Mitglieder-Team (nicht currentTeam),
     * da Fanfiction-Verwaltung eine Vereins-Funktion ist.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $membersTeam = Team::membersTeam();

        if (! $user || ! $membersTeam) {
            return false;
        }

        return $membersTeam->hasUserWithRole($user, Role::Vorstand->value)
            || $membersTeam->hasUserWithRole($user, Role::Admin->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author_type' => 'required|in:member,external',
            'user_id' => 'nullable|required_if:author_type,member|exists:users,id',
            'author_name' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'photos' => 'nullable|array|max:'.self::MAX_PHOTOS,
            'photos.*' => 'file|max:'.self::MAX_PHOTO_SIZE_KB.'|mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            'existing_photos' => 'nullable|array',
            'status' => 'required|in:draft,published',
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
            'title.required' => 'Bitte gib einen Titel an.',
            'title.max' => 'Der Titel darf maximal 255 Zeichen lang sein.',
            'author_type.required' => 'Bitte wähle einen Autorentyp aus.',
            'author_type.in' => 'Der Autorentyp ist ungültig.',
            'user_id.required_if' => 'Bitte wähle einen Autor aus den Mitgliedern aus.',
            'user_id.exists' => 'Der ausgewählte Autor existiert nicht.',
            'author_name.required' => 'Bitte gib einen Autorennamen an.',
            'author_name.max' => 'Der Autorenname darf maximal 255 Zeichen lang sein.',
            'content.required' => 'Bitte gib den Inhalt der Fanfiction ein.',
            'content.min' => 'Die Fanfiction muss mindestens 10 Zeichen lang sein.',
            'photos.max' => 'Es können maximal '.self::MAX_PHOTOS.' Fotos hochgeladen werden.',
            'photos.*.file' => 'Alle hochgeladenen Dateien müssen gültige Dateien sein.',
            'photos.*.max' => 'Jedes Foto darf maximal '.(self::MAX_PHOTO_SIZE_KB / 1024).'MB groß sein.',
            'photos.*.mimes' => 'Fotos müssen im Format '.implode(', ', self::ALLOWED_EXTENSIONS).' sein.',
            'status.required' => 'Bitte wähle einen Status aus.',
            'status.in' => 'Der Status ist ungültig.',
        ];
    }
}
