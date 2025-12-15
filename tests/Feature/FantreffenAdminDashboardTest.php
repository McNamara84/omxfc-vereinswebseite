<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FantreffenAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        
        // Attach user to team with role via pivot table
        $user->teams()->attach($team->id, [
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $user;
    }

    /** @test */
    public function admin_dashboard_is_accessible_for_admin()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $response = $this->get('/admin/fantreffen-2026');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fantreffen-admin-dashboard');
    }

    /** @test */
    public function admin_dashboard_is_accessible_for_vorstand()
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $this->actingAs($vorstand);

        $response = $this->get('/admin/fantreffen-2026');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_dashboard_is_accessible_for_kassenwart()
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $this->actingAs($kassenwart);

        $response = $this->get('/admin/fantreffen-2026');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_dashboard_is_not_accessible_for_regular_member()
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($member);

        $response = $this->get('/admin/fantreffen-2026');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_dashboard_is_not_accessible_for_guests()
    {
        $response = $this->get('/admin/fantreffen-2026');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function admin_dashboard_displays_all_registrations()
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

        $response = $this->get('/admin/fantreffen-2026');

        $response->assertSee('Max Mustermann');
        $response->assertSee('max@example.com');
        $response->assertSee('Anna Schmidt');
        $response->assertSee('anna@example.com');
    }

    /** @test */
    public function admin_dashboard_shows_correct_statistics()
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
            ->test('fantreffen-admin-dashboard')
            ->assertSet('stats.total', 3)
            ->assertSet('stats.mitglieder', 1)
            ->assertSet('stats.gaeste', 2)
            ->assertSet('stats.tshirts', 1)
            ->assertSet('stats.zahlungen_ausstehend', 2)
            ->assertSet('stats.zahlungen_offen_betrag', 35.00);
    }

    /** @test */
    public function admin_can_mark_member_registration_as_orga_team_and_make_it_free()
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
            ->test('fantreffen-admin-dashboard')
            ->call('toggleOrgaTeam', $registration->id);

        $updatedRegistration = $registration->fresh();

        $this->assertTrue($updatedRegistration->orga_team);
        $this->assertSame('free', $updatedRegistration->payment_status);
        $this->assertTrue($updatedRegistration->zahlungseingang);
        $this->assertEquals(0.00, (float) $updatedRegistration->payment_amount);
    }

    /** @test */
    public function removing_orga_team_status_recalculates_member_payment()
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $this->actingAs($admin);

        $registration = FantreffenAnmeldung::create([
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
            ->test('fantreffen-admin-dashboard')
            ->call('toggleOrgaTeam', $registration->id);

        $updated = $registration->fresh();
        $this->assertFalse($updated->orga_team);
        $this->assertSame('pending', $updated->payment_status);
        $this->assertFalse($updated->zahlungseingang);
        $this->assertEquals(25.00, (float) $updated->payment_amount);
    }

    /** @test */
    public function guests_cannot_be_marked_as_orga_team()
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
            ->test('fantreffen-admin-dashboard')
            ->call('toggleOrgaTeam', $registration->id);

        $unchanged = $registration->fresh();
        $this->assertFalse($unchanged->orga_team);
        $this->assertSame('pending', $unchanged->payment_status);
        $this->assertFalse($unchanged->zahlungseingang);
    }

    /** @test */
    public function admin_can_filter_by_member_status()
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
            ->test('fantreffen-admin-dashboard')
            ->set('filterMemberStatus', 'mitglieder')
            ->assertSee($member->vorname)
            ->assertDontSee('Max Mustermann');
    }

    /** @test */
    public function admin_can_filter_by_tshirt_status()
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
            ->test('fantreffen-admin-dashboard')
            ->set('filterTshirt', 'mit_tshirt')
            ->assertSee('Anna Schmidt')
            ->assertDontSee('Max Mustermann');
    }

    /** @test */
    public function admin_can_search_by_name()
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
            ->test('fantreffen-admin-dashboard')
            ->set('search', 'Anna')
            ->assertSee('Anna Schmidt')
            ->assertDontSee('Max Mustermann');
    }

    /** @test */
    public function admin_can_toggle_zahlungseingang()
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
            ->test('fantreffen-admin-dashboard')
            ->call('toggleZahlungseingang', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertTrue($anmeldung->zahlungseingang);

        // Toggle wieder zurück
        Livewire::actingAs($admin)
            ->test('fantreffen-admin-dashboard')
            ->call('toggleZahlungseingang', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertFalse($anmeldung->zahlungseingang);
    }

    /** @test */
    public function admin_can_toggle_tshirt_fertig()
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
            ->test('fantreffen-admin-dashboard')
            ->call('toggleTshirtFertig', $anmeldung->id);

        $anmeldung->refresh();
        $this->assertTrue($anmeldung->tshirt_fertig);
    }

    /** @test */
    public function admin_can_export_csv()
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
        
        $livewireComponent = new \App\Livewire\FantreffenAdminDashboard();
        $response = $livewireComponent->exportCsv();

        // Check that we got a StreamedResponse
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        
        // Check headers
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fantreffen-anmeldungen-', $response->headers->get('Content-Disposition'));
    }

    /** @test */
    public function admin_dashboard_paginates_results()
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

        $response = $this->actingAs($admin)->get('/admin/fantreffen-2026');

        // Should see latest 20 items first (descending order by creation)
        $response->assertSee('Person25');
        $response->assertSee('Person6');

        // Should not see oldest items on first page
        $response->assertDontSee('person5@example.com');
        $response->assertDontSee('person1@example.com');
    }

    /** @test */
    public function admin_can_delete_registration()
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
            ->test('fantreffen-admin-dashboard')
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }

    /** @test */
    public function vorstand_can_delete_registration()
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
            ->test('fantreffen-admin-dashboard')
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }

    /** @test */
    public function kassenwart_can_delete_registration()
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
            ->test('fantreffen-admin-dashboard')
            ->call('deleteAnmeldung', $anmeldung->id);

        $this->assertEquals(0, FantreffenAnmeldung::count());
    }
}
