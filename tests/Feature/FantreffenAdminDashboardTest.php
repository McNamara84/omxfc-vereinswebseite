<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenAdminDashboard;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FantreffenAdminDashboardTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
    $this->veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
});

test('admin dashboard is accessible for admin', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $response = $this->get($this->dashboardRoute());
    $response->assertStatus(200);
    $response->assertSeeLivewire('fantreffen-admin-dashboard');
});

test('legacy admin dashboard route redirects to canonical event route', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $this->get(route('admin.fantreffen.2026'))->assertRedirect(route('admin.veranstaltungen.anmeldungen', ['veranstaltung' => 'maddrax-fantreffen-2026']));
});

test('admin dashboard is accessible for vorstand', function () {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $this->actingAs($vorstand);
    $response = $this->get($this->dashboardRoute());
    $response->assertStatus(200);
});

test('admin dashboard is accessible for kassenwart', function () {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $this->actingAs($kassenwart);
    $response = $this->get($this->dashboardRoute());
    $response->assertStatus(200);
});

test('admin dashboard is not accessible for regular member', function () {
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($member);
    $response = $this->get($this->dashboardRoute());
    $response->assertStatus(403);
});

test('admin dashboard is not accessible for guests', function () {
    $response = $this->get($this->dashboardRoute());
    $response->assertRedirect('/login');
});

test('admin dashboard displays all registrations', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    FantreffenAnmeldung::create(['vorname' => 'Anna', 'nachname' => 'Schmidt', 'email' => 'anna@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 30.0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'M']);
    $response = $this->get($this->dashboardRoute());
    $response->assertSee('Max Mustermann');
    $response->assertSee('max@example.com');
    $response->assertSee('Anna Schmidt');
    $response->assertSee('anna@example.com');
});

test('admin dashboard shows correct statistics', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $member = User::factory()->create();
    $member->teams()->attach($team);
    // 2 Gäste
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    FantreffenAnmeldung::create(['vorname' => 'Anna', 'nachname' => 'Schmidt', 'email' => 'anna@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 30.0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'M']);
    // 1 Mitglied
    FantreffenAnmeldung::create(['user_id' => $member->id, 'vorname' => $member->vorname, 'nachname' => $member->nachname, 'email' => $member->email, 'ist_mitglied' => true, 'payment_status' => 'free', 'payment_amount' => 0, 'tshirt_bestellt' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->assertSet('stats.total', 3)->assertSet('stats.mitglieder', 1)->assertSet('stats.gaeste', 2)->assertSet('stats.tshirts', 1)->assertSet('stats.zahlungen_ausstehend', 2)->assertSet('stats.zahlungen_offen_betrag', 35.0);
});

test('admin can mark member registration as orga team and make it free', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $registration = FantreffenAnmeldung::create(['vorname' => 'Lena', 'nachname' => 'Licht', 'email' => 'lena@example.com', 'ist_mitglied' => true, 'payment_status' => 'pending', 'payment_amount' => 25.0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L', 'zahlungseingang' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleOrgaTeam', $registration->id);
    $updatedRegistration = $registration->fresh();
    expect($updatedRegistration->orga_team)->toBeTrue()
        ->and($updatedRegistration->payment_status)->toBe('free')
        ->and($updatedRegistration->zahlungseingang)->toBeTrue()
        ->and((float) $updatedRegistration->payment_amount)->toEqual(0.0);
});

test('removing orga team status recalculates member payment', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $zahlungsEvent = Veranstaltung::create(['titel' => 'Orga-Testevent', 'slug' => 'orga-testevent', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true, 'zahlung_aktiv' => true, 'tshirt_aktiv' => true, 'tshirt_preis' => 25]);
    $registration = FantreffenAnmeldung::create(['veranstaltung_id' => $zahlungsEvent->id, 'vorname' => 'Kai', 'nachname' => 'Kraft', 'email' => 'kai@example.com', 'ist_mitglied' => true, 'orga_team' => true, 'payment_status' => 'free', 'payment_amount' => 0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'S', 'zahlungseingang' => true]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $zahlungsEvent])->call('toggleOrgaTeam', $registration->id);
    $updated = $registration->fresh();
    expect($updated->orga_team)->toBeFalse()
        ->and($updated->payment_status)->toBe('pending')
        ->and($updated->zahlungseingang)->toBeFalse()
        ->and((float) $updated->payment_amount)->toEqual(25.0);
});

test('guests cannot be marked as orga team', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $registration = FantreffenAnmeldung::create(['vorname' => 'Nico', 'nachname' => 'Nord', 'email' => 'nico@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false, 'zahlungseingang' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleOrgaTeam', $registration->id);
    $unchanged = $registration->fresh();
    expect($unchanged->orga_team)->toBeFalse()
        ->and($unchanged->payment_status)->toBe('pending')
        ->and($unchanged->zahlungseingang)->toBeFalse();
});

test('admin can filter by member status', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $member = User::factory()->create();
    $member->teams()->attach($team);
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    FantreffenAnmeldung::create(['user_id' => $member->id, 'vorname' => $member->vorname, 'nachname' => $member->nachname, 'email' => $member->email, 'ist_mitglied' => true, 'payment_status' => 'free', 'payment_amount' => 0, 'tshirt_bestellt' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->set('filterMemberStatus', 'mitglieder')->assertSee($member->vorname)->assertDontSee('Max Mustermann');
});

test('admin can filter by tshirt status', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    FantreffenAnmeldung::create(['vorname' => 'Anna', 'nachname' => 'Schmidt', 'email' => 'anna@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 30.0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'M']);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->set('filterTshirt', 'mit_tshirt')->assertSee('Anna Schmidt')->assertDontSee('Max Mustermann');
});

test('admin can search by name', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    FantreffenAnmeldung::create(['vorname' => 'Anna', 'nachname' => 'Schmidt', 'email' => 'anna@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->set('search', 'Anna')->assertSee('Anna Schmidt')->assertDontSee('Max Mustermann');
});

test('admin can toggle zahlungseingang', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $anmeldung = FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false, 'zahlungseingang' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleZahlungseingang', $anmeldung->id);
    $anmeldung->refresh();
    expect($anmeldung->zahlungseingang)->toBeTrue();
    // Toggle wieder zurück
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleZahlungseingang', $anmeldung->id);
    $anmeldung->refresh();
    expect($anmeldung->zahlungseingang)->toBeFalse();
});

test('admin can toggle tshirt fertig', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $anmeldung = FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 30.0, 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L', 'tshirt_fertig' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleTshirtFertig', $anmeldung->id);
    $anmeldung->refresh();
    expect($anmeldung->tshirt_fertig)->toBeTrue();
});

test('admin can toggle specific merch status on order row', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $artikel = $this->createMerchartikel('Stoffbeutel', 12.0);
    $anmeldung = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Mila', 'nachname' => 'Merch', 'email' => 'mila@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 12.0, 'zahlungseingang' => false]);
    $bestellung = $anmeldung->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => false]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('toggleMerchFertig', $bestellung->id);
    expect($bestellung->fresh()->status_erledigt)->toBeTrue();
});

test('admin can filter by open merch orders', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $artikel = $this->createMerchartikel('Stoffbeutel', 12.0);
    $offen = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Offen', 'nachname' => 'Bestellt', 'email' => 'offen@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 12.0]);
    $offen->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => false]);
    $erledigt = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Erledigt', 'nachname' => 'Bestellt', 'email' => 'erledigt@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 12.0]);
    $erledigt->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => true]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->set('filterTshirtFertig', 'offen')->assertSee('Offen Bestellt')->assertDontSee('Erledigt Bestellt');
});

test('admin can filter by completed merch without matching mixed open orders', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $artikel = $this->createMerchartikel('Stoffbeutel', 12.0);
    $legacyFertig = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Legacy', 'nachname' => 'Fertig', 'email' => 'legacy-fertig@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 25.0, 'tshirt_bestellt' => true, 'tshirt_fertig' => true]);
    $gemischt = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Gemischt', 'nachname' => 'Offen', 'email' => 'gemischt-offen@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 37.0, 'tshirt_bestellt' => true, 'tshirt_fertig' => true]);
    $gemischt->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => false]);
    $vollstaendig = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => 'Vollstaendig', 'nachname' => 'Erledigt', 'email' => 'vollstaendig-erledigt@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 12.0]);
    $vollstaendig->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => true]);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->set('filterTshirtFertig', 'fertig')->assertSee($legacyFertig->full_name)->assertSee($vollstaendig->full_name)->assertDontSee($gemischt->full_name);
});

test('admin can export csv', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'mobile' => '0151 12345678', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    // Call exportCsv directly on the component instance
    $this->actingAs($admin);
    $livewireComponent = new FantreffenAdminDashboard;
    $livewireComponent->mount($this->veranstaltung);
    $response = $livewireComponent->exportCsv();
    // Check that we got a StreamedResponse
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    // Check headers
    expect($response->headers->get('Content-Type'))->toEqual('text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain($this->veranstaltung->slug.'-anmeldungen-');
});

test('admin csv export escapes quotes and sanitizes formula values', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $artikel = $this->createMerchartikel("Beutel \"Deluxe\"\nSpezial\\", 12.0);
    $anmeldung = FantreffenAnmeldung::create(['veranstaltung_id' => $this->veranstaltung->id, 'vorname' => '=SUM(1+1)', 'nachname' => 'Evil', 'email' => 'evil@example.com', 'mobile' => '+4912345', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 12.0, 'zahlungseingang' => false, 'paypal_transaction_id' => '@paypal']);
    $anmeldung->merchartikelBestellungen()->create(['veranstaltungs_merchartikel_id' => $artikel->id, 'preis_zum_bestellzeitpunkt' => 12.0, 'status_erledigt' => false]);
    $this->actingAs($admin);
    $livewireComponent = new FantreffenAdminDashboard;
    $livewireComponent->mount($this->veranstaltung);
    $response = $livewireComponent->exportCsv();
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();
    expect($csv)->toContain("'=SUM(1+1) Evil")
        ->toContain("'+4912345")
        ->toContain("'@paypal")
        ->toContain('Beutel ""Deluxe""')
        ->toContain('Spezial');
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $headers = fgetcsv($stream, escape: '');
    $row = array_combine($headers, fgetcsv($stream, escape: ''));
    expect($row['Merchandise'])->toBe("Beutel \"Deluxe\"\nSpezial\\")
        ->and($row['Name'])->toBe("'=SUM(1+1) Evil")
        ->and($row['Mobil'])->toBe("'+4912345")
        ->and($row['PayPal ID'])->toBe("'@paypal")
        ->and(fgetcsv($stream, escape: ''))->toBeFalse();
    fclose($stream);
});

test('admin dashboard paginates results', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    // Create 25 registrations (pagination is 20 per page)
    for ($i = 1; $i <= 25; $i++) {
        FantreffenAnmeldung::create(['vorname' => "Person{$i}", 'nachname' => 'Test', 'email' => "person{$i}@example.com", 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    }
    $response = $this->actingAs($admin)->get($this->dashboardRoute());
    // Should see latest 20 items first (descending order by creation)
    $response->assertSee('Person25');
    $response->assertSee('Person6');
    // Should not see oldest items on first page
    $response->assertDontSee('person5@example.com');
    $response->assertDontSee('person1@example.com');
});

test('admin can delete registration', function () {
    $admin = $this->createUserWithRole(Role::Admin);
    $anmeldung = FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    expect(FantreffenAnmeldung::count())->toEqual(1);
    Livewire::actingAs($admin)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('deleteAnmeldung', $anmeldung->id);
    expect(FantreffenAnmeldung::count())->toEqual(0);
});

test('vorstand can delete registration', function () {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $anmeldung = FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    Livewire::actingAs($vorstand)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('deleteAnmeldung', $anmeldung->id);
    expect(FantreffenAnmeldung::count())->toEqual(0);
});

test('kassenwart can delete registration', function () {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $anmeldung = FantreffenAnmeldung::create(['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'ist_mitglied' => false, 'payment_status' => 'pending', 'payment_amount' => 5.0, 'tshirt_bestellt' => false]);
    Livewire::actingAs($kassenwart)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])->call('deleteAnmeldung', $anmeldung->id);
    expect(FantreffenAnmeldung::count())->toEqual(0);
});
