<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsMerchartikel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FantreffenAnmeldungTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
    $this->veranstaltung = Veranstaltung::create(['titel' => 'Test-Fantreffen 2026', 'slug' => 'test-fantreffen-2026', 'status' => 'veroeffentlicht', 'veranstaltungsart' => 'Fantreffen', 'untertitel' => 'Dynamische Event-Testseite', 'teaser' => 'Programm, Anmeldung und aktuelle Informationen zum Testevent.', 'beschreibung' => "## ColoniaCon am selben Wochenende!\n\nMaddrax-Panel um 14:00 Uhr mit Michael Schönenbröcher und Wolfgang Hohlbein (unter Vorbehalt).\n\nVorstellung des OMXFC und des Maddraxikons um 10:40 Uhr.\n\nDie Location liegt nur fünf Minuten zu Fuß entfernt. Weitere Infos: coloniacon-tng.de/2026", 'datum_von' => '2026-05-09 19:00:00', 'ort_name' => "L'Osteria Köln Mülheim", 'ort_adresse' => 'Düsseldorfer Str. 1-3, 51063 Köln', 'maps_url' => 'https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5', 'anmeldung_aktiv' => true, 'zahlung_aktiv' => true, 'tshirt_aktiv' => true, 'tshirt_deadline' => '2026-02-28 23:59:59', 'merch_deadline' => '2027-02-28 23:59:59', 'gastgebuehr' => 5, 'tshirt_preis' => 25, 'ist_highlight' => true]);
    $tshirt = $this->veranstaltung->merchartikel()->create(['bezeichnung' => 'T-Shirt', 'preis' => 25, 'sort_order' => 0, 'is_active' => true]);
    foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $index => $groesse) {
        $tshirt->varianten()->create(['bezeichnung' => $groesse, 'sort_order' => $index, 'is_active' => true]);
    }
    $this->veranstaltung->merchartikel()->create(['bezeichnung' => 'Stoffbeutel', 'beschreibung' => 'Limitierter Beutel zur Veranstaltung.', 'preis' => 12, 'sort_order' => 1, 'is_active' => true]);
});

test('fantreffen page is accessible without authentication', function () {
    $response = $this->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('Test-Fantreffen 2026');
});

test('guest can register without tshirt', function () {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'mobile' => '0151 12345678', 'tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'payment_amount' => 5.0]);
});

test('guest can register with tshirt', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 15, 12));
    try {
        Mail::fake();
        $response = $this->post($this->storeUrl(), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L', 'website' => '', '_form_token' => $this->validFormToken()]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', ['payment_amount' => 30.0]);
        $this->assertDatabaseHas('fantreffen_anmeldung_merchartikel', ['preis_zum_bestellzeitpunkt' => 25.0]);
    } finally {
        Carbon::setTestNow();
    }
});

test('legacy route can register with tshirt', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 2, 15, 12));
    try {
        Mail::fake();
        $legacyVeranstaltung = Veranstaltung::query()->where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
        $legacyVeranstaltung->forceFill(['status' => 'veroeffentlicht', 'anmeldung_aktiv' => true, 'merch_deadline' => Carbon::now()->addWeek()])->save();
        $tshirt = $legacyVeranstaltung->merchartikel()->firstOrCreate(['bezeichnung' => 'T-Shirt'], ['preis' => 25, 'sort_order' => 0, 'is_active' => true]);
        $tshirt->forceFill(['preis' => 25, 'sort_order' => 0, 'is_active' => true])->save();
        $groesse = $tshirt->varianten()->firstOrCreate(['bezeichnung' => 'L'], ['sort_order' => 0, 'is_active' => true]);
        $groesse->forceFill(['sort_order' => 0, 'is_active' => true])->save();
        $response = $this->post(route('fantreffen.2026.store'), ['vorname' => 'Lara', 'nachname' => 'Legacy', 'email' => 'legacy@example.com', 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L', 'website' => '', '_form_token' => $this->validFormToken()]);
        $response->assertRedirect();
        $this->assertDatabaseHas('fantreffen_anmeldungen', ['veranstaltung_id' => $legacyVeranstaltung->id, 'email' => 'legacy@example.com', 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L']);
        $this->assertDatabaseHas('fantreffen_anmeldung_merchartikel', ['veranstaltungs_merchartikel_id' => $tshirt->id, 'veranstaltungs_merchvariante_id' => $groesse->id, 'preis_zum_bestellzeitpunkt' => 25.0]);
    } finally {
        Carbon::setTestNow();
    }
});

test('guest can register with multiple merch items', function (): void {
    Mail::fake();
    $tshirt = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $this->veranstaltung->id)->where('bezeichnung', 'T-Shirt')->firstOrFail();
    $bag = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $this->veranstaltung->id)->where('bezeichnung', 'Stoffbeutel')->firstOrFail();
    $sizeL = $tshirt->varianten()->where('bezeichnung', 'L')->value('id');
    $response = $this->post($this->storeUrl(), ['vorname' => 'Mira', 'nachname' => 'Merch', 'email' => 'mira@example.com', 'website' => '', '_form_token' => $this->validFormToken(), 'merch' => [$tshirt->id => ['selected' => '1', 'variant_id' => (string) $sizeL], $bag->id => ['selected' => '1']]]);
    $response->assertRedirect();
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['email' => 'mira@example.com', 'payment_amount' => 42.0]);
    expect(FantreffenAnmeldung::query()->firstOrFail()->merchartikelBestellungen()->count())->toBe(2);
});

test('variant is required for selected merch with variants', function (): void {
    Mail::fake();
    $tshirt = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $this->veranstaltung->id)->where('bezeichnung', 'T-Shirt')->firstOrFail();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Nora', 'nachname' => 'Variant', 'email' => 'nora@example.com', 'website' => '', '_form_token' => $this->validFormToken(), 'merch' => [$tshirt->id => ['selected' => '1']]]);
    $response->assertSessionHasErrors('merch.'.$tshirt->id.'.variant_id');
    $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'nora@example.com']);
});

test('variant id must be scalar for selected merch', function (): void {
    Mail::fake();
    $tshirt = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $this->veranstaltung->id)->where('bezeichnung', 'T-Shirt')->firstOrFail();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Nina', 'nachname' => 'Array', 'email' => 'nina@example.com', 'website' => '', '_form_token' => $this->validFormToken(), 'merch' => [$tshirt->id => ['selected' => '1', 'variant_id' => ['id' => 1]]]]);
    $response->assertSessionHasErrors('merch.'.$tshirt->id.'.variant_id');
    $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'nina@example.com']);
});

test('invalid variant input can be rendered after redirect without warning', function (): void {
    Mail::fake();
    $tshirt = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $this->veranstaltung->id)->where('bezeichnung', 'T-Shirt')->firstOrFail();
    $response = $this->from($this->showUrl())->followingRedirects()->post($this->storeUrl(), ['vorname' => 'Nina', 'nachname' => 'Redirect', 'email' => 'nina-redirect@example.com', 'website' => '', '_form_token' => $this->validFormToken(), 'merch' => [$tshirt->id => ['selected' => '1', 'variant_id' => ['id' => 1]]]]);
    $response->assertOk();
    $response->assertSee('Bitte wähle eine gültige Variante aus.');
});

test('logged in member can register without payment', function () {
    Mail::fake();
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $user = User::factory()->create();
    $user->teams()->attach($team);
    $this->actingAs($user);
    $response = $this->post($this->storeUrl(), ['tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['user_id' => $user->id, 'payment_amount' => 0]);
});

test('activity is logged when member registers for fantreffen', function () {
    Mail::fake();
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $user = User::factory()->create(['vorname' => 'Alex']);
    $user->teams()->attach($team);
    $this->actingAs($user);
    $response = $this->post($this->storeUrl(), ['tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $anmeldung = FantreffenAnmeldung::query()->first();
    expect($anmeldung)->not->toBeNull();
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_type' => FantreffenAnmeldung::class, 'subject_id' => $anmeldung->id, 'action' => 'fantreffen_registered']);
});

test('guest registration logs activity without user attribution', function () {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Sam', 'nachname' => 'Guest', 'email' => 'sam@example.com', 'tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $anmeldung = FantreffenAnmeldung::query()->first();
    expect($anmeldung)->not->toBeNull();
    $this->assertDatabaseHas('activities', ['user_id' => null, 'subject_type' => FantreffenAnmeldung::class, 'subject_id' => $anmeldung->id, 'action' => 'fantreffen_registered']);
});

test('payment confirmation page shows paypal button', function () {
    $anmeldung = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false, 'zahlungseingang' => false]);
    $response = $this->get($this->confirmationUrl($anmeldung));
    $response->assertStatus(200);
    $response->assertSee('Zu zahlender Betrag');
    $response->assertSee('5,00 €');
});

test('guest confirmation page requires valid signature', function (): void {
    $anmeldung = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false, 'zahlungseingang' => false]);
    $this->get(route('veranstaltungen.bestaetigung', ['veranstaltung' => $this->veranstaltung, 'id' => $anmeldung->id]))->assertForbidden();
    $this->get(route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id]))->assertForbidden();
});

test('coloniacon banner shows panel info', function () {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('ColoniaCon am selben Wochenende!');
    $response->assertSee('Maddrax-Panel');
    $response->assertSee('14:00 Uhr');
});

test('event description strips raw html from markdown', function (): void {
    $this->veranstaltung->update(['beschreibung' => "**Sicherer Inhalt**\n\n<img src=x onerror=alert('xss')>"]);
    $renderedBeschreibung = $this->veranstaltung->fresh()->html_beschreibung;
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertOk();
    $response->assertSeeText('Sicherer Inhalt');
    expect($renderedBeschreibung)->toContain('<strong>Sicherer Inhalt</strong>')->not->toContain('<img');
});

test('event sections strip raw html from markdown', function (): void {
    $abschnitt = $this->veranstaltung->abschnitte()->create(['titel' => 'FAQ', 'schluessel' => 'faq', 'markdown_inhalt' => "## Frage\n\n<img src=x onerror=alert('xss')>\n\nAntwort", 'sort_order' => 1, 'is_visible' => true]);
    $renderedAbschnitt = $abschnitt->fresh()->html_inhalt;
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertOk();
    $response->assertSeeText('Frage');
    $response->assertSeeText('Antwort');
    expect($renderedAbschnitt)->toContain('<h2>Frage</h2>')->not->toContain('<img');
});

test('coloniacon banner shows author names', function () {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('Michael Schönenbröcher');
    $response->assertSee('Wolfgang Hohlbein (unter Vorbehalt)');
});

test('coloniacon banner shows omxfc presentation', function () {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('Vorstellung des OMXFC und des Maddraxikons');
    $response->assertSee('10:40 Uhr');
});

test('coloniacon banner shows walking distance', function () {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('fünf Minuten zu Fuß');
});

test('coloniacon banner links to coloniacon website', function () {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('coloniacon-tng.de/2026');
});

test('honeypot blocks bot submissions', function (): void {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Bot', 'nachname' => 'Spammer', 'email' => 'bot@spam.com', 'website' => 'http://spam-link.com', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect($this->showUrl());
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'bot@spam.com']);
});

test('honeypot allows legitimate submissions', function (): void {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'legit@example.com', 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['email' => 'legit@example.com']);
});

test('timing check blocks instant submissions', function (): void {
    Mail::fake();
    // Hohen Schwellenwert setzen, damit der Test deterministisch bleibt,
    // auch auf langsamen CI-Umgebungen (Token ist immer "zu frisch")
    $originalValue = config('services.fantreffen.min_form_time');
    config(['services.fantreffen.min_form_time' => 9999]);
    try {
        $token = Crypt::encryptString((string) time());
        $response = $this->post($this->storeUrl(), ['vorname' => 'Bot', 'nachname' => 'Fast', 'email' => 'fast@bot.com', 'website' => '', '_form_token' => $token]);
        $response->assertRedirect($this->showUrl());
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'fast@bot.com']);
    } finally {
        config(['services.fantreffen.min_form_time' => $originalValue]);
    }
});

test('missing form token is rejected', function (): void {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Bot', 'nachname' => 'NoToken', 'email' => 'notoken@bot.com', 'website' => '']);
    $response->assertRedirect($this->showUrl());
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'notoken@bot.com']);
});

test('manipulated form token is rejected', function (): void {
    Mail::fake();
    $response = $this->post($this->storeUrl(), ['vorname' => 'Hacker', 'nachname' => 'Bob', 'email' => 'hacker@evil.com', 'website' => '', '_form_token' => 'manipulated-garbage-value']);
    $response->assertRedirect($this->showUrl());
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'hacker@evil.com']);
});

test('duplicate email is rejected for guests', function (): void {
    Mail::fake();
    FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Erster', 'nachname' => 'Anmelder', 'email' => 'doppelt@example.com', 'ist_mitglied' => false, 'tshirt_bestellt' => false, 'zahlungseingang' => false, 'payment_amount' => 5.0, 'payment_status' => 'pending']);
    $response = $this->post($this->storeUrl(), ['vorname' => 'Zweiter', 'nachname' => 'Anmelder', 'email' => 'doppelt@example.com', 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('fantreffen_anmeldungen', 1);
});

test('duplicate registration is rejected for members', function (): void {
    Mail::fake();
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $user = User::factory()->create();
    $user->teams()->attach($team);
    FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'user_id' => $user->id, 'vorname' => $user->vorname, 'nachname' => $user->nachname, 'email' => $user->email, 'ist_mitglied' => true, 'tshirt_bestellt' => false, 'zahlungseingang' => false, 'payment_amount' => 0, 'payment_status' => 'free']);
    $response = $this->actingAs($user)->post($this->storeUrl(), ['tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('fantreffen_anmeldungen', 1);
});

test('form page includes honeypot field', function (): void {
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('name="website"', false);
    $response->assertSee('name="_form_token"', false);
});

test('rate limiter blocks after fifteen requests', function (): void {
    Mail::fake();
    // Rate Limiter für diesen Test gezielt aktivieren
    $originalValue = config('services.fantreffen.disable_rate_limit');
    config(['services.fantreffen.disable_rate_limit' => false]);
    try {
        for ($i = 1; $i <= 15; $i++) {
            $response = $this->post($this->storeUrl(), ['vorname' => "User{$i}", 'nachname' => 'Test', 'email' => "user{$i}@example.com", 'website' => '', '_form_token' => $this->validFormToken()]);
            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
        }
        // 16. Request sollte gedrosselt werden
        $response = $this->post($this->storeUrl(), ['vorname' => 'Blocked', 'nachname' => 'User', 'email' => 'blocked@example.com', 'website' => '', '_form_token' => $this->validFormToken()]);
        $response->assertStatus(429);
        $this->assertDatabaseMissing('fantreffen_anmeldungen', ['email' => 'blocked@example.com']);
    } finally {
        config(['services.fantreffen.disable_rate_limit' => $originalValue]);
    }
});

test('rate limiter can be disabled via config', function (): void {
    Mail::fake();
    $originalValue = config('services.fantreffen.disable_rate_limit');
    config(['services.fantreffen.disable_rate_limit' => true]);
    try {
        // 20 Requests sollten alle durchgehen wenn Rate-Limit deaktiviert ist
        for ($i = 1; $i <= 20; $i++) {
            $response = $this->post($this->storeUrl(), ['vorname' => "User{$i}", 'nachname' => 'Test', 'email' => "user{$i}@example.com", 'website' => '', '_form_token' => $this->validFormToken()]);
            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
        }
    } finally {
        config(['services.fantreffen.disable_rate_limit' => $originalValue]);
    }
});
