<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MitgliedschaftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_form_displays_accessible_fields(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<x-input', $html, 'Compiled view should not contain unresolved Blade components.');

        // Prüfe, dass alle Formularfelder per name-Attribut gerendert werden
        $expectedFields = [
            'vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt',
            'land', 'mail', 'passwort', 'passwort_confirmation',
            'mitgliedsbeitrag', 'telefon', 'verein_gefunden',
        ];

        foreach ($expectedFields as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Feld '$field' fehlt im Formular.");
        }

        // Prüfe, dass Labels für Pflichtfelder als <legend> gerendert werden (maryUI/form-select-Pattern)
        $requiredLabels = ['Vorname', 'Nachname', 'Straße', 'Hausnummer', 'Postleitzahl', 'Stadt', 'Land', 'Mailadresse', 'Passwort'];
        foreach ($requiredLabels as $label) {
            $this->assertMatchesRegularExpression(
                '/<legend\b[^>]*>\s*' . preg_quote($label, '/') . '/si',
                $html,
                "Label '$label' fehlt als sichtbares <legend>-Element im Formular."
            );
        }

        // Prüfe, dass Hints für Passwort und Telefon vorhanden sind (maryUI fieldset-label)
        $this->assertStringContainsString('Mindestens 6 Zeichen.', $html);
        $this->assertStringContainsString('Bitte wiederhole dein Passwort.', $html);

        // Prüfe Beitrag-Slider
        $this->assertStringContainsString('id="mitgliedsbeitrag"', $html);
        $this->assertStringContainsString('id="beitrag-output"', $html);

        // Satzung-Checkbox
        $this->assertStringContainsString('id="satzung_check"', $html);
    }

    public function test_membership_application_creates_user_and_assigns_anwaerter_role(): void
    {
        Mail::fake();

        $data = [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'mail' => 'max@example.com',
            'passwort' => 'secret123',
            'passwort_confirmation' => 'secret123',
            'mitgliedsbeitrag' => 12,
            'telefon' => '0123456789',
            'verein_gefunden' => 'Internet',
        ];

        $response = $this->postJson(route('mitglied.store'), $data);

        $response->assertOk()->assertJson(['success' => true]);

        $user = User::where('email', 'max@example.com')->first();
        $this->assertNotNull($user);

        $team = Team::membersTeam();
        $this->assertTrue($team->users()->where('user_id', $user->id)->wherePivot('role', Role::Anwaerter->value)->exists());

        Mail::assertQueued(MitgliedAntragEingereicht::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_membership_application_requires_first_name(): void
    {
        Mail::fake();

        $data = [
            'nachname' => 'Mustermann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'mail' => 'max@example.com',
            'passwort' => 'secret123',
            'passwort_confirmation' => 'secret123',
            'mitgliedsbeitrag' => 12,
        ];

        $response = $this->postJson(route('mitglied.store'), $data);

        $response->assertStatus(422)->assertJsonValidationErrors(['vorname']);
    }

    public function test_membership_form_renders_all_required_fields(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();

        $expectedFields = [
            'vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt',
            'land', 'mail', 'passwort', 'passwort_confirmation',
            'mitgliedsbeitrag', 'telefon', 'verein_gefunden', 'satzung_check',
        ];

        foreach ($expectedFields as $fieldName) {
            $response->assertSee('name="'.$fieldName.'"', false);
        }
    }

    public function test_membership_form_script_logs_missing_field_warnings(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('[Mitgliedschaftsformular] Feld mit ID "', $html);
    }

}
