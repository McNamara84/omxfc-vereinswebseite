<?php

namespace Tests\Feature;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FantreffenAnmeldungTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function fantreffen_page_is_accessible_without_authentication()
    {
        $response = $this->get('/maddrax-fantreffen-2026');
        $response->assertStatus(200);
        $response->assertSee('Maddrax-Fantreffen 2026');
    }

    /** @test */
    public function guest_can_register_without_tshirt()
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

    /** @test */
    public function guest_can_register_with_tshirt()
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

    /** @test */
    public function logged_in_member_can_register_without_payment()
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

    /** @test */
    public function payment_confirmation_page_shows_paypal_button()
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
}