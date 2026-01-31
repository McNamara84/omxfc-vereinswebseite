<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request für Arbeitsgruppen-Erstellung und -Bearbeitung.
 */
class ArbeitsgruppeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Admins können alle AGs erstellen/bearbeiten.
     * Team-Leiter können ihre eigene AG bearbeiten.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Prüfe Admin-Rolle explizit im Mitglieder-Team (nicht currentTeam)
        $membersTeam = Team::membersTeam();
        if ($membersTeam && $membersTeam->hasUserWithRole($user, Role::Admin->value)) {
            return true;
        }

        // Bei Update: Leiter darf seine eigene AG bearbeiten
        if ($this->route('team') instanceof Team) {
            return $this->route('team')->user_id === $user->id;
        }

        // Bei Create: Nur Admins
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'leader_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'meeting_schedule' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
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
            'name.required' => 'Bitte gib einen Namen für die Arbeitsgruppe an.',
            'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
            'leader_id.required' => 'Bitte wähle einen Leiter aus.',
            'leader_id.exists' => 'Der ausgewählte Leiter existiert nicht.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
            'meeting_schedule.max' => 'Der Besprechungszeitplan darf maximal 255 Zeichen lang sein.',
            'logo.image' => 'Das Logo muss ein Bild sein.',
            'logo.max' => 'Das Logo darf maximal 2MB groß sein.',
        ];
    }
}
