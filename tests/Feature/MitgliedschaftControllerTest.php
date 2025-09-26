<?php

namespace Tests\Feature;

use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Enums\Role;

class MitgliedschaftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_form_displays_accessible_fields(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<x-input', $html, 'Compiled view should not contain unresolved Blade components.');

        $fields = [
            'vorname' => ['aria' => 'aria-describedby="vorname-error"', 'ids' => ['vorname-error']],
            'nachname' => ['aria' => 'aria-describedby="nachname-error"', 'ids' => ['nachname-error']],
            'strasse' => ['aria' => 'aria-describedby="strasse-error"', 'ids' => ['strasse-error']],
            'hausnummer' => ['aria' => 'aria-describedby="hausnummer-error"', 'ids' => ['hausnummer-error']],
            'plz' => ['aria' => 'aria-describedby="plz-error"', 'ids' => ['plz-error']],
            'stadt' => ['aria' => 'aria-describedby="stadt-error"', 'ids' => ['stadt-error']],
            'land' => ['aria' => 'aria-describedby="land-error"', 'ids' => ['land-error']],
            'mail' => ['aria' => 'aria-describedby="mail-error"', 'ids' => ['mail-error']],
            'passwort' => ['aria' => 'aria-describedby="passwort-hint passwort-error"', 'ids' => ['passwort-hint', 'passwort-error']],
            'passwort_confirmation' => ['aria' => 'aria-describedby="passwort_confirmation-hint passwort_confirmation-error"', 'ids' => ['passwort_confirmation-hint', 'passwort_confirmation-error']],
            'telefon' => ['aria' => 'aria-describedby="telefon-hint telefon-error"', 'ids' => ['telefon-hint', 'telefon-error']],
            'verein_gefunden' => ['aria' => 'aria-describedby="verein_gefunden-error"', 'ids' => ['verein_gefunden-error']],
        ];

        foreach ($fields as $field => $expectation) {
            $this->assertStringContainsString('id="' . $field . '"', $html);
            $this->assertStringContainsString($expectation['aria'], $html);

            foreach ($expectation['ids'] as $id) {
                $this->assertStringContainsString('id="' . $id . '"', $html);
            }

            $this->assertStringContainsString('data-error-for="' . $field . '"', $html);
        }
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
}
