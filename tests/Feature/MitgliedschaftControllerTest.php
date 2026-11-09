<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MitgliedWerdenForm;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('membership form displays accessible fields', function (): void {
    $response = $this->get('/mitglied-werden');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->not->toContain('<x-input');
    // Prüfe, dass alle Formularfelder per name-Attribut gerendert werden
    $expectedFields = ['vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort', 'passwort_confirmation', 'mitgliedsbeitrag', 'telefon', 'verein_gefunden'];
    foreach ($expectedFields as $field) {
        expect($html)->toContain('name="'.$field.'"');
    }
    // Prüfe, dass Labels für Pflichtfelder gerendert werden (x-input als <span>, x-form-select als <legend>)
    $requiredLabels = ['Vorname', 'Nachname', 'Straße', 'Hausnummer', 'Postleitzahl', 'Stadt', 'Land', 'Mailadresse', 'Passwort'];
    foreach ($requiredLabels as $label) {
        expect($html)->toMatch('/<(?:legend|span)\b[^>]*>\s*'.preg_quote($label, '/').'/si');
    }
    // Prüfe, dass Hints für Passwort und Telefon vorhanden sind (maryUI fieldset-label)
    expect($html)->toContain('Mindestens 8 Zeichen.');
    expect($html)->toContain('Bitte wiederhole dein Passwort.');
    // Prüfe Beitrag-Slider
    expect($html)->toContain('id="mitgliedsbeitrag"');
    expect($html)->toContain('id="beitrag-output"');
    // Satzung-Checkbox (maryUI prefixes the id with a hash, so check by name attribute)
    expect($html)->toContain('name="satzung_check"');
});

test('membership application creates user and assigns anwaerter role', function (): void {
    Mail::fake();
    $data = ['vorname' => 'Max', 'nachname' => 'Mustermann', 'strasse' => 'Musterstraße', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Musterstadt', 'land' => 'Deutschland', 'mail' => 'max@example.com', 'passwort' => 'secret123', 'passwort_confirmation' => 'secret123', 'mitgliedsbeitrag' => 12, 'telefon' => '0123456789', 'verein_gefunden' => 'Internet'];
    $response = $this->postJson(route('mitglied.store'), $data);
    $response->assertOk()->assertJson(['success' => true]);
    $user = User::where('email', 'max@example.com')->first();
    expect($user)->not->toBeNull();
    $team = Team::membersTeam();
    expect($team->users()->where('user_id', $user->id)->wherePivot('role', Role::Anwaerter->value)->exists())->toBeTrue();
    Mail::assertQueued(MitgliedAntragEingereicht::class, fn ($mail) => $mail->hasTo($user->email));
});

test('membership application rejects passwords below the default security minimum', function (): void {
    Mail::fake();
    $response = $this->postJson(route('mitglied.store'), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'strasse' => 'Musterstraße', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Musterstadt', 'land' => 'Deutschland', 'mail' => 'short-password@example.com', 'passwort' => '1234567', 'passwort_confirmation' => '1234567', 'mitgliedsbeitrag' => 12]);
    $response->assertUnprocessable()->assertJsonValidationErrors(['passwort']);
    $this->assertDatabaseMissing('users', ['email' => 'short-password@example.com']);
    Mail::assertNothingQueued();
});

test('membership form uses a required message for an empty password', function (): void {
    $component = Livewire::test(MitgliedWerdenForm::class)->call('submit')->assertHasErrors(['passwort' => 'required']);
    expect($component->errors()->first('passwort'))->toBe('Bitte gib ein Passwort ein.');
});

test('membership form uses the default minimum message for a short password', function (): void {
    $component = Livewire::test(MitgliedWerdenForm::class)->set('passwort', '1234567')->set('passwort_confirmation', '1234567')->call('submit')->assertHasErrors(['passwort']);
    expect($component->errors()->first('passwort'))->toBe('Das Passwort muss mindestens 8 Zeichen lang sein.');
});

test('membership application requires first name', function (): void {
    Mail::fake();
    $data = ['nachname' => 'Mustermann', 'strasse' => 'Musterstraße', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Musterstadt', 'land' => 'Deutschland', 'mail' => 'max@example.com', 'passwort' => 'secret123', 'passwort_confirmation' => 'secret123', 'mitgliedsbeitrag' => 12];
    $response = $this->postJson(route('mitglied.store'), $data);
    $response->assertStatus(422)->assertJsonValidationErrors(['vorname']);
});

test('membership form renders all required fields', function (): void {
    $response = $this->get('/mitglied-werden');
    $response->assertOk();
    $expectedFields = ['vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort', 'passwort_confirmation', 'mitgliedsbeitrag', 'telefon', 'verein_gefunden', 'satzung_check'];
    foreach ($expectedFields as $fieldName) {
        $response->assertSee('name="'.$fieldName.'"', false);
    }
});

test('membership form has livewire component', function (): void {
    $response = $this->get('/mitglied-werden');
    $response->assertOk();
    $response->assertSeeLivewire(MitgliedWerdenForm::class);
});
