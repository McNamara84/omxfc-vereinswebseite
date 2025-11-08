<?php

namespace Tests\Feature;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FantreffenAnmeldungTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function fantreffen_page_is_accessible_without_authentication()
    {
        $response = $this->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fantreffen-anmeldung');
    }

    /** @test */
    public function fantreffen_page_displays_event_information()
    {
        $response = $this->get('/maddrax-fantreffen-2026');

        $response->assertSee('Maddrax-Fantreffen 2026');
        $response->assertSee('9. Mai 2026');
        $response->assertSee('L´Osteria Köln Mülheim');
        $response->assertSee('19:00 Uhr');
    }

    /** @test */
    public function guest_can_register_without_tshirt()
    {
        Mail::fake();

        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->set('mobile', '0151 12345678')
            ->set('tshirt_bestellt', false)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'mobile' => '0151 12345678',
            'tshirt_bestellt' => false,
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
        ]);

        Mail::assertQueued(FantreffenAnmeldungBestaetigung::class, function ($mail) {
            return $mail->hasTo('max@example.com');
        });

        Mail::assertQueued(FantreffenNeueAnmeldung::class, function ($mail) {
            return $mail->hasTo('vorstand@maddrax-fanclub.de');
        });
    }

    /** @test */
    public function guest_can_register_with_tshirt()
    {
        Mail::fake();

        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->set('tshirt_bestellt', true)
            ->set('tshirt_groesse', 'L')
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
            'payment_amount' => 30.00, // 5€ Teilnahme + 25€ T-Shirt
        ]);
    }

    /** @test */
    public function logged_in_member_can_register_without_payment()
    {
        Mail::fake();

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create([
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
        ]);
        $user->teams()->attach($team);

        $this->actingAs($user);

        Livewire::test('fantreffen-anmeldung')
            ->set('mobile', '0151 98765432')
            ->set('tshirt_bestellt', false)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'user_id' => $user->id,
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
        ]);
    }

    /** @test */
    public function logged_in_member_pays_only_for_tshirt()
    {
        Mail::fake();

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $this->actingAs($user);

        Livewire::test('fantreffen-anmeldung')
            ->set('tshirt_bestellt', true)
            ->set('tshirt_groesse', 'XL')
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'user_id' => $user->id,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'XL',
            'payment_status' => 'pending',
            'payment_amount' => 25.00, // Nur T-Shirt
        ]);
    }

    /** @test */
    public function registration_requires_vorname_for_guests()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->call('submit')
            ->assertHasErrors(['vorname' => 'required']);
    }

    /** @test */
    public function registration_requires_nachname_for_guests()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('email', 'max@example.com')
            ->call('submit')
            ->assertHasErrors(['nachname' => 'required']);
    }

    /** @test */
    public function registration_requires_email_for_guests()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->call('submit')
            ->assertHasErrors(['email' => 'required']);
    }

    /** @test */
    public function registration_requires_valid_email()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'invalid-email')
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);
    }

    /** @test */
    public function tshirt_size_is_required_when_tshirt_is_ordered()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->set('tshirt_bestellt', true)
            ->call('submit')
            ->assertHasErrors(['tshirt_groesse']);
    }

    /** @test */
    public function tshirt_size_must_be_valid()
    {
        Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->set('tshirt_bestellt', true)
            ->set('tshirt_groesse', 'INVALID')
            ->call('submit')
            ->assertHasErrors(['tshirt_groesse']);
    }

    /** @test */
    public function shows_warning_when_guest_email_is_already_registered()
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $user->teams()->attach($team);

        Livewire::test('fantreffen-anmeldung')
            ->set('email', 'existing@example.com')
            ->assertSet('showEmailWarning', true);
    }

    /** @test */
    public function logged_in_member_does_not_need_to_provide_personal_data()
    {
        Mail::fake();

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $this->actingAs($user);

        // Should not require vorname, nachname, email
        Livewire::test('fantreffen-anmeldung')
            ->call('submit')
            ->assertHasNoErrors(['vorname', 'nachname', 'email'])
            ->assertRedirect();
    }

    /** @test */
    public function redirects_to_payment_confirmation_page_after_registration()
    {
        Mail::fake();

        $component = Livewire::test('fantreffen-anmeldung')
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->call('submit');

        $component->assertRedirect();
        $redirectUrl = $component->effects['redirect'];
        $this->assertStringContainsString('/maddrax-fantreffen-2026/bestaetigung/', $redirectUrl);
    }

    /** @test */
    public function payment_confirmation_page_is_accessible_with_valid_id()
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => $user->id,
            'vorname' => $user->vorname,
            'nachname' => $user->nachname,
            'email' => $user->email,
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertStatus(200);
        $response->assertSeeLivewire('fantreffen-zahlungsbestaetigung');
    }

    /** @test */
    public function guest_can_access_payment_confirmation_with_session_token()
    {
        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        // Simulate session token
        session()->put('fantreffen_anmeldung_' . $anmeldung->id, true);

        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function guest_cannot_access_other_users_payment_confirmation()
    {
        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        // No session token
        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function member_cannot_access_other_members_payment_confirmation()
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        
        $user1 = User::factory()->create();
        $user1->teams()->attach($team);
        
        $user2 = User::factory()->create();
        $user2->teams()->attach($team);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => $user2->id,
            'vorname' => $user2->vorname,
            'nachname' => $user2->nachname,
            'email' => $user2->email,
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
        ]);

        $this->actingAs($user1);

        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function payment_confirmation_shows_paypal_button_for_pending_payments()
    {
        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        session()->put('fantreffen_anmeldung_' . $anmeldung->id, true);

        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertSee('Jetzt mit PayPal zahlen');
        $response->assertSee('paypal.me');
    }

    /** @test */
    public function payment_confirmation_shows_no_payment_required_for_free_registrations()
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $anmeldung = FantreffenAnmeldung::create([
            'user_id' => $user->id,
            'vorname' => $user->vorname,
            'nachname' => $user->nachname,
            'email' => $user->email,
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");

        $response->assertSee('Keine Zahlung erforderlich');
        $response->assertDontSee('paypal.me');
    }
}
