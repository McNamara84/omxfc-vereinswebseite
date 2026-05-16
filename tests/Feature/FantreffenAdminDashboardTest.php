<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenAdminDashboard;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsMerchartikel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class FantreffenAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
        $this->veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
    }

    protected function dashboardRoute(): string
    {
        return route('admin.veranstaltungen.anmeldungen', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        // Attach user to team with role via pivot table
        $user->teams()->attach($team->id, [
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createMerchartikel(string $bezeichnung = 'T-Shirt', float $preis = 25.00, array $varianten = []): VeranstaltungsMerchartikel
    {
        $artikel = $this->veranstaltung->merchartikel()->create([
            'bezeichnung' => $bezeichnung,
            'preis' => $preis,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        foreach ($varianten as $index => $variante) {
            $artikel->varianten()->create([
                'bezeichnung' => $variante,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        return $artikel;
    }

    #[Test]
    public function test_admin_dashboard_is_accessible_for_admin()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $response = $this->get($this->dashboardRoute());

        $response->assertStatus(200);
        $response->assertSeeLivewire('fantreffen-admin-dashboard');
    }

    #[Test]
    public function test_legacy_admin_dashboard_route_redirects_to_canonical_event_route(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $this->get(route('admin.fantreffen.2026'))
            ->assertRedirect(route('admin.veranstaltungen.anmeldungen', ['veranstaltung' => 'maddrax-fantreffen-2026']));
    }

    #[Test]
    public function test_admin_dashboard_is_accessible_for_vorstand()
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $this->actingAs($vorstand);

        $response = $this->get($this->dashboardRoute());

        $response->assertStatus(200);
    }

    #[Test]
    public function test_admin_dashboard_is_accessible_for_kassenwart()
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $this->actingAs($kassenwart);

        $response = $this->get($this->dashboardRoute());

        $response->assertStatus(200);
    }

    #[Test]
    public function test_admin_dashboard_is_not_accessible_for_regular_member()
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($member);

        $response = $this->get($this->dashboardRoute());

        $response->assertStatus(403);
    }

    #[Test]
    public function test_admin_dashboard_is_not_accessible_for_guests()
    {
        $response = $this->get($this->dashboardRoute());

        $response->assertRedirect('/login');
    }

    #[Test]
    public function test_admin_dashboard_displays_all_registrations()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        FantreffenAnmeldung::create([
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 30.00,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'M',
        ]);

        $response = $this->get($this->dashboardRoute());

        $response->assertSee('Max Mustermann');
        $response->assertSee('max@example.com');
        $response->assertSee('Anna Schmidt');
        $response->assertSee('anna@example.com');
    }

    #[Test]
    public function test_admin_dashboard_shows_correct_statistics()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $member = User::factory()->create();
        $member->teams()->attach($team);

        // 2 Gäste
        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        FantreffenAnmeldung::create([
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 30.00,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'M',
        ]);

        // 1 Mitglied
        FantreffenAnmeldung::create([
            'user_id' => $member->id,
            'vorname' => $member->vorname,
            'nachname' => $member->nachname,
            'email' => $member->email,
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->assertSet('stats.total', 3)
            ->assertSet('stats.mitglieder', 1)
            ->assertSet('stats.gaeste', 2)
            ->assertSet('stats.tshirts', 1)
            ->assertSet('stats.zahlungen_ausstehend', 2)
            ->assertSet('stats.zahlungen_offen_betrag', 35.00);
    }

    #[Test]
    public function test_admin_can_mark_member_registration_as_orga_team_and_make_it_free()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $registration = FantreffenAnmeldung::create([
            'vorname' => 'Lena',
            'nachname' => 'Licht',
            'email' => 'lena@example.com',
            'ist_mitglied' => true,
            'payment_status' => 'pending',
            'payment_amount' => 25.00,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
            'zahlungseingang' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleOrgaTeam', $registration->id);

        $updatedRegistration = $registration->fresh();

        $this->assertTrue($updatedRegistration->orga_team);
        $this->assertSame('free', $updatedRegistration->payment_status);
        $this->assertTrue($updatedRegistration->zahlungseingang);
        $this->assertEquals(0.00, (float) $updatedRegistration->payment_amount);
    }

    #[Test]
    public function test_removing_orga_team_status_recalculates_member_payment()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $zahlungsEvent = Veranstaltung::create([
            'titel' => 'Orga-Testevent',
            'slug' => 'orga-testevent',
            'status' => 'veroeffentlicht',
            'anmeldung_aktiv' => true,
            'zahlung_aktiv' => true,
            'tshirt_aktiv' => true,
            'tshirt_preis' => 25,
        ]);

        $registration = FantreffenAnmeldung::create([
            'veranstaltung_id' => $zahlungsEvent->id,
            'vorname' => 'Kai',
            'nachname' => 'Kraft',
            'email' => 'kai@example.com',
            'ist_mitglied' => true,
            'orga_team' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'S',
            'zahlungseingang' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $zahlungsEvent])
            ->call('toggleOrgaTeam', $registration->id);

        $updated = $registration->fresh();
        $this->assertFalse($updated->orga_team);
        $this->assertSame('pending', $updated->payment_status);
        $this->assertFalse($updated->zahlungseingang);
        $this->assertEquals(25.00, (float) $updated->payment_amount);
    }

    #[Test]
    public function test_guests_cannot_be_marked_as_orga_team()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $registration = FantreffenAnmeldung::create([
            'vorname' => 'Nico',
            'nachname' => 'Nord',
            'email' => 'nico@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
            'zahlungseingang' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleOrgaTeam', $registration->id);

        $unchanged = $registration->fresh();
        $this->assertFalse($unchanged->orga_team);
        $this->assertSame('pending', $unchanged->payment_status);
        $this->assertFalse($unchanged->zahlungseingang);
    }

    #[Test]
    public function test_admin_can_filter_by_member_status()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $member = User::factory()->create();
        $member->teams()->attach($team);

        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        FantreffenAnmeldung::create([
            'user_id' => $member->id,
            'vorname' => $member->vorname,
            'nachname' => $member->nachname,
            'email' => $member->email,
            'ist_mitglied' => true,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->set('filterMemberStatus', 'mitglieder')
            ->assertSee($member->vorname)
            ->assertDontSee('Max Mustermann');
    }

    #[Test]
    public function test_admin_can_filter_by_tshirt_status()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        FantreffenAnmeldung::create([
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 30.00,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'M',
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->set('filterTshirt', 'mit_tshirt')
            ->assertSee('Anna Schmidt')
            ->assertDontSee('Max Mustermann');
    }

    #[Test]
    public function test_admin_can_search_by_name()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        FantreffenAnmeldung::create([
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'email' => 'anna@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->set('search', 'Anna')
            ->assertSee('Anna Schmidt')
            ->assertDontSee('Max Mustermann');
    }

    #[Test]
    public function test_admin_can_toggle_zahlungseingang()
    {
        $admin = $this->createUserWithRole(Role::Admin);

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

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleZahlungseingang', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertTrue($anmeldung->zahlungseingang);

        // Toggle wieder zurück
        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleZahlungseingang', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertFalse($anmeldung->zahlungseingang);
    }

    #[Test]
    public function test_admin_can_toggle_tshirt_fertig()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 30.00,
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
            'tshirt_fertig' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleTshirtFertig', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertTrue($anmeldung->tshirt_fertig);
    }

    #[Test]
    public function test_admin_can_toggle_specific_merch_status_on_order_row(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $artikel = $this->createMerchartikel('Stoffbeutel', 12.00);

        $anmeldung = FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Mila',
            'nachname' => 'Merch',
            'email' => 'mila@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 12.00,
            'zahlungseingang' => false,
        ]);

        $bestellung = $anmeldung->merchartikelBestellungen()->create([
            'veranstaltungs_merchartikel_id' => $artikel->id,
            'preis_zum_bestellzeitpunkt' => 12.00,
            'status_erledigt' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('toggleMerchFertig', $bestellung->id);

        $this->assertTrue($bestellung->fresh()->status_erledigt);
    }

    #[Test]
    public function test_admin_can_filter_by_open_merch_orders(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $artikel = $this->createMerchartikel('Stoffbeutel', 12.00);

        $offen = FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Offen',
            'nachname' => 'Bestellt',
            'email' => 'offen@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 12.00,
        ]);
        $offen->merchartikelBestellungen()->create([
            'veranstaltungs_merchartikel_id' => $artikel->id,
            'preis_zum_bestellzeitpunkt' => 12.00,
            'status_erledigt' => false,
        ]);

        $erledigt = FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => 'Erledigt',
            'nachname' => 'Bestellt',
            'email' => 'erledigt@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 12.00,
        ]);
        $erledigt->merchartikelBestellungen()->create([
            'veranstaltungs_merchartikel_id' => $artikel->id,
            'preis_zum_bestellzeitpunkt' => 12.00,
            'status_erledigt' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->set('filterTshirtFertig', 'offen')
            ->assertSee('Offen Bestellt')
            ->assertDontSee('Erledigt Bestellt');
    }

    #[Test]
    public function test_admin_can_export_csv()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'mobile' => '0151 12345678',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        // Call exportCsv directly on the component instance
        $this->actingAs($admin);

        $livewireComponent = new FantreffenAdminDashboard;
        $livewireComponent->mount($this->veranstaltung);
        $response = $livewireComponent->exportCsv();

        // Check that we got a StreamedResponse
        $this->assertInstanceOf(StreamedResponse::class, $response);

        // Check headers
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($this->veranstaltung->slug.'-anmeldungen-', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function test_admin_csv_export_escapes_quotes_and_sanitizes_formula_values(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $artikel = $this->createMerchartikel("Beutel \"Deluxe\"\nSpezial", 12.00);

        $anmeldung = FantreffenAnmeldung::create([
            'veranstaltung_id' => $this->veranstaltung->id,
            'vorname' => '=SUM(1+1)',
            'nachname' => 'Evil',
            'email' => 'evil@example.com',
            'mobile' => '+4912345',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 12.00,
            'zahlungseingang' => false,
            'paypal_transaction_id' => '@paypal',
        ]);

        $anmeldung->merchartikelBestellungen()->create([
            'veranstaltungs_merchartikel_id' => $artikel->id,
            'preis_zum_bestellzeitpunkt' => 12.00,
            'status_erledigt' => false,
        ]);

        $this->actingAs($admin);

        $livewireComponent = new FantreffenAdminDashboard;
        $livewireComponent->mount($this->veranstaltung);
        $response = $livewireComponent->exportCsv();

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString("'=SUM(1+1) Evil", $csv);
        $this->assertStringContainsString("'+4912345", $csv);
        $this->assertStringContainsString("'@paypal", $csv);
        $this->assertStringContainsString('Beutel ""Deluxe""', $csv);
        $this->assertStringContainsString("Spezial", $csv);
    }

    #[Test]
    public function test_admin_dashboard_paginates_results()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        // Create 25 registrations (pagination is 20 per page)
        for ($i = 1; $i <= 25; $i++) {
            FantreffenAnmeldung::create([
                'vorname' => "Person{$i}",
                'nachname' => 'Test',
                'email' => "person{$i}@example.com",
                'ist_mitglied' => false,
                'payment_status' => 'pending',
                'payment_amount' => 5.00,
                'tshirt_bestellt' => false,
            ]);
        }

        $response = $this->actingAs($admin)->get($this->dashboardRoute());

        // Should see latest 20 items first (descending order by creation)
        $response->assertSee('Person25');
        $response->assertSee('Person6');

        // Should not see oldest items on first page
        $response->assertDontSee('person5@example.com');
        $response->assertDontSee('person1@example.com');
    }

    #[Test]
    public function test_admin_can_delete_registration()
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        $this->assertEquals(1, FantreffenAnmeldung::count());

        Livewire::actingAs($admin)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }

    #[Test]
    public function test_vorstand_can_delete_registration()
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        Livewire::actingAs($vorstand)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }

    #[Test]
    public function test_kassenwart_can_delete_registration()
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);

        $anmeldung = FantreffenAnmeldung::create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'ist_mitglied' => false,
            'payment_status' => 'pending',
            'payment_amount' => 5.00,
            'tshirt_bestellt' => false,
        ]);

        Livewire::actingAs($kassenwart)
            ->test(FantreffenAdminDashboard::class, ['veranstaltung' => $this->veranstaltung])
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }
}
