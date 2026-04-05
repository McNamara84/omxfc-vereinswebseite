<?php

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

class MitgliedWerdenFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_renders_on_page(): void
    {
        $response = $this->get('/mitglied-werden');

        $response->assertOk();
        $response->assertSeeLivewire(MitgliedWerdenForm::class);
    }

    public function test_successful_registration_creates_user_and_team(): void
    {
        Mail::fake();

        Livewire::test(MitgliedWerdenForm::class)
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('strasse', 'Teststraße')
            ->set('hausnummer', '42')
            ->set('plz', '12345')
            ->set('stadt', 'Berlin')
            ->set('land', 'Deutschland')
            ->set('mail', 'max.neu@example.com')
            ->set('passwort', 'geheim123')
            ->set('passwort_confirmation', 'geheim123')
            ->set('mitgliedsbeitrag', 24)
            ->set('satzung_check', true)
            ->call('submit')
            ->assertRedirect(route('mitglied.werden.erfolgreich'));

        $this->assertDatabaseHas('users', [
            'email' => 'max.neu@example.com',
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
        ]);

        $user = User::where('email', 'max.neu@example.com')->first();
        $team = Team::where('name', 'Mitglieder')->first();

        $this->assertNotNull($team);
        $this->assertEquals($team->id, $user->current_team_id);
        $this->assertTrue(
            $team->users()->where('user_id', $user->id)->wherePivot('role', Role::Anwaerter->value)->exists()
        );
    }

    public function test_registration_sends_confirmation_mail(): void
    {
        Mail::fake();

        Livewire::test(MitgliedWerdenForm::class)
            ->set('vorname', 'Anna')
            ->set('nachname', 'Testerin')
            ->set('strasse', 'Mailweg')
            ->set('hausnummer', '1')
            ->set('plz', '54321')
            ->set('stadt', 'Hamburg')
            ->set('land', 'Deutschland')
            ->set('mail', 'anna@example.com')
            ->set('passwort', 'sicher456')
            ->set('passwort_confirmation', 'sicher456')
            ->set('satzung_check', true)
            ->call('submit');

        Mail::assertQueued(MitgliedAntragEingereicht::class, function ($mail) {
            return $mail->hasTo('anna@example.com');
        });
    }

    public function test_satzung_check_required(): void
    {
        Livewire::test(MitgliedWerdenForm::class)
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('strasse', 'Teststraße')
            ->set('hausnummer', '42')
            ->set('plz', '12345')
            ->set('stadt', 'Berlin')
            ->set('land', 'Deutschland')
            ->set('mail', 'max2@example.com')
            ->set('passwort', 'geheim123')
            ->set('passwort_confirmation', 'geheim123')
            ->set('satzung_check', false)
            ->call('submit')
            ->assertHasErrors(['satzung_check']);
    }

    public function test_password_confirmation_must_match(): void
    {
        Livewire::test(MitgliedWerdenForm::class)
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('strasse', 'Teststraße')
            ->set('hausnummer', '42')
            ->set('plz', '12345')
            ->set('stadt', 'Berlin')
            ->set('land', 'Deutschland')
            ->set('mail', 'max3@example.com')
            ->set('passwort', 'geheim123')
            ->set('passwort_confirmation', 'falsch456')
            ->set('satzung_check', true)
            ->call('submit')
            ->assertHasErrors(['passwort']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        Livewire::test(MitgliedWerdenForm::class)
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('strasse', 'Teststraße')
            ->set('hausnummer', '42')
            ->set('plz', '12345')
            ->set('stadt', 'Berlin')
            ->set('land', 'Deutschland')
            ->set('mail', 'existing@example.com')
            ->set('passwort', 'geheim123')
            ->set('passwort_confirmation', 'geheim123')
            ->set('satzung_check', true)
            ->call('submit')
            ->assertHasErrors(['mail']);
    }

    public function test_realtime_validation_on_blur(): void
    {
        Livewire::test(MitgliedWerdenForm::class)
            ->set('mail', 'keine-email')
            ->assertHasErrors(['mail']);
    }

    public function test_required_fields_validated(): void
    {
        Livewire::test(MitgliedWerdenForm::class)
            ->set('satzung_check', true)
            ->call('submit')
            ->assertHasErrors(['vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort']);
    }

    public function test_mitgliedsbeitrag_must_be_in_range(): void
    {
        Livewire::test(MitgliedWerdenForm::class)
            ->set('mitgliedsbeitrag', 5)
            ->set('vorname', 'Max')
            ->set('nachname', 'Test')
            ->set('strasse', 'S')
            ->set('hausnummer', '1')
            ->set('plz', '12345')
            ->set('stadt', 'X')
            ->set('land', 'Deutschland')
            ->set('mail', 'beitrag@example.com')
            ->set('passwort', 'geheim123')
            ->set('passwort_confirmation', 'geheim123')
            ->set('satzung_check', true)
            ->call('submit')
            ->assertHasErrors(['mitgliedsbeitrag']);
    }
}
