<?php

namespace Tests\Feature;

use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\TestCase;

class FantreffenAnmeldungTest extends TestCase
{
    use CreatesFantreffenFormToken;
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);

        $this->veranstaltung = Veranstaltung::create([
            'titel' => 'Test-Fantreffen 2026',
            'slug' => 'test-fantreffen-2026',
            'status' => 'veroeffentlicht',
            'veranstaltungsart' => 'Fantreffen',
            'untertitel' => 'Dynamische Event-Testseite',
            'teaser' => 'Programm, Anmeldung und aktuelle Informationen zum Testevent.',
            'beschreibung' => "## ColoniaCon am selben Wochenende!\n\nMaddrax-Panel um 14:00 Uhr mit Michael Schönenbröcher und Wolfgang Hohlbein (unter Vorbehalt).\n\nVorstellung des OMXFC und des Maddraxikons um 10:40 Uhr.\n\nDie Location liegt nur fünf Minuten zu Fuß entfernt. Weitere Infos: coloniacon-tng.de/2026",
            'datum_von' => '2026-05-09 19:00:00',
            'ort_name' => "L'Osteria Köln Mülheim",
            'ort_adresse' => 'Düsseldorfer Str. 1-3, 51063 Köln',
            'maps_url' => 'https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5',
            'anmeldung_aktiv' => true,
            'zahlung_aktiv' => true,
            'tshirt_aktiv' => true,
            'tshirt_deadline' => '2026-02-28 23:59:59',
            'gastgebuehr' => 5,
            'tshirt_preis' => 25,
            'ist_highlight' => true,
        ]);
    }

    protected function showUrl(): string
    {
        return route('veranstaltungen.show', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function storeUrl(): string
    {
        return route('veranstaltungen.anmeldung.store', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function confirmationUrl(FantreffenAnmeldung $anmeldung): string
    {
        return URL::signedRoute('veranstaltungen.bestaetigung', ['veranstaltung' => $this->veranstaltung, 'id' => $anmeldung->id]);
    }

    public function test_fantreffen_page_is_accessible_without_authentication()
    {
        $response = $this->get($this->showUrl());
        $response->assertStatus(200);
        $response->assertSee('Test-Fantreffen 2026');
    }

    public function test_guest_can_register_without_tshirt()
    {
        Mail::fake();
        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'mobile' => '0151 12345678',
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
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
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 12));
        try {
            Mail::fake();
            $response = $this->post($this->storeUrl(), [
                'vorname' => 'Max',
                'nachname' => 'Mustermann',
                'email' => 'max@example.com',
                'tshirt_bestellt' => true,
                'tshirt_groesse' => 'L',
                'website' => '',
                '_form_token' => $this->validFormToken(),
            ]);
            $response->assertRedirect();
            $this->assertDatabaseHas('fantreffen_anmeldungen', [
                'payment_amount' => 30.00,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_logged_in_member_can_register_without_payment()
    {
        Mail::fake();
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $this->actingAs($user);
        $response = $this->post($this->storeUrl(), [
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
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

        $response = $this->post($this->storeUrl(), [
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
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

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Sam',
            'nachname' => 'Guest',
            'email' => 'sam@example.com',
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
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
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
        ]);
        $response = $this->get($this->confirmationUrl($anmeldung));
        $response->assertStatus(200);
        $response->assertSee('Zu zahlender Betrag');
        $response->assertSee('5,00 €');
    }

    public function test_guest_confirmation_page_requires_valid_signature(): void
    {
        $anmeldung = FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
        ]);

        $this->get(route('veranstaltungen.bestaetigung', ['veranstaltung' => $this->veranstaltung, 'id' => $anmeldung->id]))
            ->assertForbidden();

        $this->get(route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id]))
            ->assertForbidden();
    }

    public function test_coloniacon_banner_shows_panel_info()
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('ColoniaCon am selben Wochenende!');
        $response->assertSee('Maddrax-Panel');
        $response->assertSee('14:00 Uhr');
    }

    public function test_event_description_strips_raw_html_from_markdown(): void
    {
        $this->veranstaltung->update([
            'beschreibung' => "**Sicherer Inhalt**\n\n<img src=x onerror=alert('xss')>",
        ]);

        $renderedBeschreibung = $this->veranstaltung->fresh()->html_beschreibung;

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertOk();
        $response->assertSeeText('Sicherer Inhalt');
        $this->assertStringContainsString('<strong>Sicherer Inhalt</strong>', $renderedBeschreibung);
        $this->assertStringNotContainsString('<img', $renderedBeschreibung);
    }

    public function test_event_sections_strip_raw_html_from_markdown(): void
    {
        $abschnitt = $this->veranstaltung->abschnitte()->create([
            'titel' => 'FAQ',
            'schluessel' => 'faq',
            'markdown_inhalt' => "## Frage\n\n<img src=x onerror=alert('xss')>\n\nAntwort",
            'sort_order' => 1,
            'is_visible' => true,
        ]);

        $renderedAbschnitt = $abschnitt->fresh()->html_inhalt;

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertOk();
        $response->assertSeeText('Frage');
        $response->assertSeeText('Antwort');
        $this->assertStringContainsString('<h2>Frage</h2>', $renderedAbschnitt);
        $this->assertStringNotContainsString('<img', $renderedAbschnitt);
    }

    public function test_coloniacon_banner_shows_author_names()
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('Michael Schönenbröcher');
        $response->assertSee('Wolfgang Hohlbein (unter Vorbehalt)');
    }

    public function test_coloniacon_banner_shows_omxfc_presentation()
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('Vorstellung des OMXFC und des Maddraxikons');
        $response->assertSee('10:40 Uhr');
    }

    public function test_coloniacon_banner_shows_walking_distance()
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('fünf Minuten zu Fuß');
    }

    public function test_coloniacon_banner_links_to_coloniacon_website()
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('coloniacon-tng.de/2026');
    }

    // === Spam-Schutz Tests ===

    public function test_honeypot_blocks_bot_submissions(): void
    {
        Mail::fake();

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Bot',
            'nachname' => 'Spammer',
            'email' => 'bot@spam.com',
            'website' => 'http://spam-link.com',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertRedirect($this->showUrl());
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'bot@spam.com']);
    }

    public function test_honeypot_allows_legitimate_submissions(): void
    {
        Mail::fake();

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'legit@example.com',
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', ['email' => 'legit@example.com']);
    }

    public function test_timing_check_blocks_instant_submissions(): void
    {
        Mail::fake();
        // Hohen Schwellenwert setzen, damit der Test deterministisch bleibt,
        // auch auf langsamen CI-Umgebungen (Token ist immer "zu frisch")
        $originalValue = config('services.fantreffen.min_form_time');
        config(['services.fantreffen.min_form_time' => 9999]);

        try {
            $token = Crypt::encryptString((string) time());

            $response = $this->post($this->storeUrl(), [
                'vorname' => 'Bot',
                'nachname' => 'Fast',
                'email' => 'fast@bot.com',
                'website' => '',
                '_form_token' => $token,
            ]);

            $response->assertRedirect($this->showUrl());
            $response->assertSessionHasErrors('error');
            $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'fast@bot.com']);
        } finally {
            config(['services.fantreffen.min_form_time' => $originalValue]);
        }
    }

    public function test_missing_form_token_is_rejected(): void
    {
        Mail::fake();

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Bot',
            'nachname' => 'NoToken',
            'email' => 'notoken@bot.com',
            'website' => '',
        ]);

        $response->assertRedirect($this->showUrl());
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'notoken@bot.com']);
    }

    public function test_manipulated_form_token_is_rejected(): void
    {
        Mail::fake();

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Hacker',
            'nachname' => 'Bob',
            'email' => 'hacker@evil.com',
            'website' => '',
            '_form_token' => 'manipulated-garbage-value',
        ]);

        $response->assertRedirect($this->showUrl());
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'hacker@evil.com']);
    }

    public function test_duplicate_email_is_rejected_for_guests(): void
    {
        Mail::fake();

        FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Erster',
            'nachname' => 'Anmelder',
            'email' => 'doppelt@example.com',
            'ist_mitglied' => false,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
            'payment_amount' => 5.00,
            'payment_status' => 'pending',
        ]);

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Zweiter',
            'nachname' => 'Anmelder',
            'email' => 'doppelt@example.com',
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('fantreffen_anmeldungen', 1);
    }

    public function test_duplicate_registration_is_rejected_for_members(): void
    {
        Mail::fake();

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'user_id' => $user->id,
            'vorname' => $user->vorname,
            'nachname' => $user->nachname,
            'email' => $user->email,
            'ist_mitglied' => true,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
            'payment_amount' => 0,
            'payment_status' => 'free',
        ]);

        $response = $this->actingAs($user)->post($this->storeUrl(), [
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('fantreffen_anmeldungen', 1);
    }

    public function test_form_page_includes_honeypot_field(): void
    {
        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('name="website"', false);
        $response->assertSee('name="_form_token"', false);
    }

    public function test_rate_limiter_blocks_after_fifteen_requests(): void
    {
        Mail::fake();

        // Rate Limiter für diesen Test gezielt aktivieren
        $originalValue = config('services.fantreffen.disable_rate_limit');
        config(['services.fantreffen.disable_rate_limit' => false]);

        try {
            for ($i = 1; $i <= 15; $i++) {
                $response = $this->post($this->storeUrl(), [
                    'vorname' => "User{$i}",
                    'nachname' => 'Test',
                    'email' => "user{$i}@example.com",
                    'website' => '',
                    '_form_token' => $this->validFormToken(),
                ]);
                $response->assertRedirect();
                $response->assertSessionHasNoErrors();
            }

            // 16. Request sollte gedrosselt werden
            $response = $this->post($this->storeUrl(), [
                'vorname' => 'Blocked',
                'nachname' => 'User',
                'email' => 'blocked@example.com',
                'website' => '',
                '_form_token' => $this->validFormToken(),
            ]);
            $response->assertStatus(429);
            $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'blocked@example.com']);
        } finally {
            config(['services.fantreffen.disable_rate_limit' => $originalValue]);
        }
    }

    public function test_rate_limiter_can_be_disabled_via_config(): void
    {
        Mail::fake();

        $originalValue = config('services.fantreffen.disable_rate_limit');
        config(['services.fantreffen.disable_rate_limit' => true]);

        try {
            // 20 Requests sollten alle durchgehen wenn Rate-Limit deaktiviert ist
            for ($i = 1; $i <= 20; $i++) {
                $response = $this->post($this->storeUrl(), [
                    'vorname' => "User{$i}",
                    'nachname' => 'Test',
                    'email' => "user{$i}@example.com",
                    'website' => '',
                    '_form_token' => $this->validFormToken(),
                ]);
                $response->assertRedirect();
                $response->assertSessionHasNoErrors();
            }
        } finally {
            config(['services.fantreffen.disable_rate_limit' => $originalValue]);
        }
    }
}
