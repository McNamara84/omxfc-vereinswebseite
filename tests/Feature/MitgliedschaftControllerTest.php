<?php

namespace Tests\Feature;

use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MitgliedschaftControllerTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertTrue($team->users()->where('user_id', $user->id)->wherePivot('role', 'Anwärter')->exists());

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
