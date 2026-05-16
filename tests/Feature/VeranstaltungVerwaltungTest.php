<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsAbschnitt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VeranstaltungVerwaltungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $user->teams()->attach($team->id, [
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createUserWithRoleAndDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);
        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);

        $managementTeam->users()->attach($user->id, [
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user->id, [
            'role' => Role::Mitglied->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    public function test_admin_can_open_event_management_index(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->withoutVite()->actingAs($admin)->get(route('admin.veranstaltungen.index'));

        $response->assertOk();
        $response->assertSee('Veranstaltungen verwalten');
        $response->assertSee('Jubiläumsfeier zu Band 700');
    }

    public function test_regular_member_cannot_open_event_management_index(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $response = $this->actingAs($member)->get(route('admin.veranstaltungen.index'));

        $response->assertForbidden();
    }

    #[TestWith([Role::Admin->value])]
    #[TestWith([Role::Vorstand->value])]
    public function test_management_user_with_other_active_team_can_open_event_management_index(string $roleValue): void
    {
        $user = $this->createUserWithRoleAndDifferentCurrentTeam(Role::from($roleValue));

        $response = $this->withoutVite()->actingAs($user)->get(route('admin.veranstaltungen.index'));

        $response->assertOk();
        $response->assertSee('Veranstaltungen verwalten');
    }

    #[TestWith([Role::Admin->value])]
    #[TestWith([Role::Vorstand->value])]
    public function test_management_user_with_other_active_team_can_open_event_registration_list(string $roleValue): void
    {
        $user = $this->createUserWithRoleAndDifferentCurrentTeam(Role::from($roleValue));
        $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();

        $response = $this->withoutVite()->actingAs($user)->get(route('admin.veranstaltungen.anmeldungen', $veranstaltung));

        $response->assertOk();
        $response->assertSee('Anmeldungen');
    }

    public function test_event_management_index_marks_archived_events_as_still_accessible_for_registration_lists(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->withoutVite()->actingAs($admin)->get(route('admin.veranstaltungen.index'));

        $response->assertOk();
        $response->assertSee('Archivierte Veranstaltung');
        $response->assertSee('Anmeldeliste weiterhin verfügbar.');
    }

    public function test_admin_can_create_event_with_structured_data(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->actingAs($admin)->post(route('admin.veranstaltungen.store'), [
            'titel' => 'Leserabend 2027',
            'slug' => 'leserabend-2027',
            'status' => 'veroeffentlicht',
            'veranstaltungsart' => 'Leserabend',
            'untertitel' => 'Ein Abend für die Community',
            'teaser' => 'Testevent mit dynamischen Feldern.',
            'beschreibung' => 'Beschreibung im Markdown-Format',
            'datum_von' => '2027-02-20 19:30:00',
            'ort_name' => 'Bürgerzentrum Köln',
            'ort_adresse' => 'Köln',
            'anmeldung_aktiv' => '1',
            'zahlung_aktiv' => '1',
            'gastgebuehr' => '12.50',
            'tshirt_preis' => '29.00',
            'ist_highlight' => '1',
        ]);

        $veranstaltung = Veranstaltung::query()->where('slug', 'leserabend-2027')->first();

        $response->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
        $this->assertNotNull($veranstaltung);
        $this->assertSame('Leserabend 2027', $veranstaltung->titel);
        $this->assertTrue($veranstaltung->anmeldung_aktiv);
        $this->assertTrue($veranstaltung->zahlung_aktiv);
        $this->assertTrue($veranstaltung->ist_highlight);

        $this->assertFalse(Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail()->ist_highlight);
    }

    public function test_admin_can_add_and_update_markdown_section(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.veranstaltungen.abschnitte.store', $veranstaltung), [
            'titel' => 'FAQ',
            'schluessel' => 'faq',
            'markdown_inhalt' => "## Frage\nAntwort",
            'sort_order' => 8,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));

        $abschnitt = VeranstaltungsAbschnitt::query()
            ->where('veranstaltung_id', $veranstaltung->id)
            ->where('schluessel', 'faq')
            ->first();

        $this->assertNotNull($abschnitt);

        $this->actingAs($admin)->put(route('admin.veranstaltungen.abschnitte.update', [$veranstaltung, $abschnitt]), [
            'titel' => 'FAQ aktualisiert',
            'schluessel' => 'faq',
            'markdown_inhalt' => "## Neue Frage\nNeue Antwort",
            'sort_order' => 3,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));

        $abschnitt->refresh();
        $this->assertSame('FAQ aktualisiert', $abschnitt->titel);
        $this->assertSame(3, $abschnitt->sort_order);
        $this->assertStringContainsString('Neue Antwort', $abschnitt->markdown_inhalt);
    }
}