<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Jetstream;
use Livewire\Component;

class MitgliedWerdenForm extends Component
{
    public string $vorname = '';

    public string $nachname = '';

    public string $strasse = '';

    public string $hausnummer = '';

    public string $plz = '';

    public string $stadt = '';

    public string $land = '';

    public string $mail = '';

    public string $passwort = '';

    public string $passwort_confirmation = '';

    public int $mitgliedsbeitrag = 12;

    public string $telefon = '';

    public string $verein_gefunden = '';

    public bool $satzung_check = false;

    public bool $submitting = false;

    protected function rules(): array
    {
        return [
            'vorname' => 'required|string|max:255',
            'nachname' => 'required|string|max:255',
            'strasse' => 'required|string|max:255',
            'hausnummer' => 'required|string|max:10',
            'plz' => 'required|string|max:10',
            'stadt' => 'required|string|max:255',
            'land' => 'required|string',
            'mail' => 'required|email|unique:users,email',
            'passwort' => 'required|confirmed|min:6',
            'mitgliedsbeitrag' => 'required|numeric|min:12|max:120',
            'telefon' => 'nullable|string|max:20',
            'verein_gefunden' => 'nullable|string|max:255',
            'satzung_check' => 'accepted',
        ];
    }

    protected function messages(): array
    {
        return [
            'vorname.required' => 'Vorname ist erforderlich.',
            'nachname.required' => 'Nachname ist erforderlich.',
            'strasse.required' => 'Straße ist erforderlich.',
            'hausnummer.required' => 'Hausnummer ist erforderlich.',
            'plz.required' => 'Bitte gültige PLZ eingeben.',
            'stadt.required' => 'Stadt ist erforderlich.',
            'land.required' => 'Bitte wähle dein Land.',
            'mail.required' => 'Bitte gültige Mailadresse eingeben.',
            'mail.email' => 'Bitte gültige Mailadresse eingeben.',
            'mail.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'passwort.required' => 'Passwort mindestens 6 Zeichen.',
            'passwort.min' => 'Passwort mindestens 6 Zeichen.',
            'passwort.confirmed' => 'Passwörter stimmen nicht überein.',
            'satzung_check.accepted' => 'Du musst die Satzung akzeptieren.',
        ];
    }

    public function updated(string $propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    public function submit(): void
    {
        $this->validate();

        $this->submitting = true;

        try {
            $team = Jetstream::newTeamModel()->firstOrCreate(
                ['name' => 'Mitglieder'],
                ['user_id' => 1, 'personal_team' => false]
            );

            $user = User::create([
                'name' => $this->vorname . ' ' . $this->nachname,
                'email' => $this->mail,
                'password' => Hash::make($this->passwort),
                'vorname' => $this->vorname,
                'nachname' => $this->nachname,
                'strasse' => $this->strasse,
                'hausnummer' => $this->hausnummer,
                'plz' => $this->plz,
                'stadt' => $this->stadt,
                'land' => $this->land,
                'telefon' => $this->telefon ?: null,
                'verein_gefunden' => $this->verein_gefunden ?: null,
                'mitgliedsbeitrag' => $this->mitgliedsbeitrag,
            ]);

            $team->users()->attach($user, ['role' => Role::Anwaerter->value]);
            $user->switchTeam($team);

            Mail::to($user->email)->queue(new MitgliedAntragEingereicht($user));

            $this->redirect(route('mitglied.werden.erfolgreich'));
        } catch (\Throwable $e) {
            $this->submitting = false;

            throw $e;
        }
    }

    public function render()
    {
        return view('livewire.mitglied-werden-form');
    }
}
