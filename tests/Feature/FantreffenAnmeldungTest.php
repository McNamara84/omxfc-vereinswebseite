<?php

namespace Tests\Feature;

use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FantreffenAnmeldungTest extends TestCase
{
    use RefreshDatabase;

    public function test_fantreffen_page_is_accessible_without_authentication()
    {
        $response = $this->get('/maddrax-fantreffen-2026');
        $response->assertStatus(200);
        $response->assertSee('Maddrax-Fantreffen 2026');
    }

    public function test_guest_can_register_without_tshirt()
    {
        Mail::fake();
        $response = $this->post('/maddrax-fantreffen-2026', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'mobile' => '0151 12345678',
            'tshirt_bestellt' => false,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'payment_amount' => 5.00,
        ]);
    }

    public function test_guest_can_register_with_tshirt()
    {
        Mail::fake();
        $response = $this->post('/maddrax-fantreffen-2026', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'payment_amount' => 30.00,
        ]);
    }

    public function test_logged_in_member_can_register_without_payment()
    {
        Mail::fake();
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $this->actingAs($user);
        $response = $this->post('/maddrax-fantreffen-2026', [
            'tshirt_bestellt' => false,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'user_id' => $user->id,
            'payment_amount' => 0,
        ]);
    }

    public function test_activity_is_logged_when_member_registers_for_fantreffen()
    {
        Mail::fake();
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create([
            'vorname' => 'Alex',
        ]);
        $user->teams()->attach($team);
        $this->actingAs($user);

        $response = $this->post('/maddrax-fantreffen-2026', [
            'tshirt_bestellt' => false,
        ]);

        $response->assertRedirect();
        $anmeldung = FantreffenAnmeldung::first();

        $this->assertNotNull($anmeldung);
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => FantreffenAnmeldung::class,
            'subject_id' => $anmeldung->id,
            'action' => 'fantreffen_registered',
        ]);
    }

    public function test_guest_registration_logs_activity_without_user_attribution()
    {
        Mail::fake();

        $response = $this->post('/maddrax-fantreffen-2026', [
            'vorname' => 'Sam',
            'nachname' => 'Guest',
            'email' => 'sam@example.com',
            'tshirt_bestellt' => false,
        ]);

        $response->assertRedirect();
        $anmeldung = FantreffenAnmeldung::first();

        $this->assertNotNull($anmeldung);
        $this->assertDatabaseHas('activities', [
            'user_id' => null,
            'subject_type' => FantreffenAnmeldung::class,
            'subject_id' => $anmeldung->id,
            'action' => 'fantreffen_registered',
        ]);
    }

    public function test_payment_confirmation_page_shows_paypal_button()
    {
        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
        ]);
        $response = $this->get("/maddrax-fantreffen-2026/bestaetigung/{$anmeldung->id}");
        $response->assertStatus(200);
        $response->assertSee('PayPal');
    }

    public function test_coloniacon_banner_shows_panel_info()
    {
        $response = $this->withoutVite()->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('ColoniaCon am selben Wochenende!');
        $response->assertSee('Maddrax-Panel');
        $response->assertSee('14:00 Uhr');
    }

    public function test_coloniacon_banner_shows_author_names()
    {
        $response = $this->withoutVite()->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('Michael Schönenbröcher');
        $response->assertSee('Wolfgang Hohlbein (unter Vorbehalt)');
    }

    public function test_coloniacon_banner_shows_omxfc_presentation()
    {
        $response = $this->withoutVite()->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('Vorstellung des OMXFC und des Maddraxikons');
        $response->assertSee('10:40 Uhr');
    }

    public function test_coloniacon_banner_shows_walking_distance()
    {
        $response = $this->withoutVite()->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('fünf Minuten zu Fuß');
    }

    public function test_coloniacon_banner_links_to_coloniacon_website()
    {
        $response = $this->withoutVite()->get('/maddrax-fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSee('coloniacon-tng.de/2026');
    }
}
