<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\TestCase;

class VeranstaltungAnmeldungTest extends TestCase
{
    use CreatesUserWithRole;
    use CreatesFantreffenFormToken;
    use RefreshDatabase;

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $user = $this->createUserWithRole($role);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    public function test_published_event_page_is_accessible_via_slug(): void
    {
        Config::set('app.testing_minimal_layout', true);

        $veranstaltung = Veranstaltung::create([
            'titel' => 'Jubiläumsfeier Band 700',
            'slug' => 'test-event-dynamisch',
            'status' => 'veroeffentlicht',
            'untertitel' => 'Das nächste Community-Treffen',
            'teaser' => 'Feiere mit uns Band 700.',
            'datum_von' => '2026-11-14 18:00:00',
            'ort_name' => 'Cinedom Köln',
            'anmeldung_aktiv' => true,
        ]);

        $response = $this->withoutVite()->get(route('veranstaltungen.show', $veranstaltung->slug));

        $response->assertOk();
        $response->assertSee('Jubiläumsfeier Band 700');
        $response->assertSee('Feiere mit uns Band 700.');
    }

    public function test_legacy_fantreffen_route_redirects_permanently_to_canonical_archiv_event(): void
    {
        $archivEvent = Veranstaltung::query()->where('slug', 'maddrax-fantreffen-2026')->firstOrFail();

        $this->get(route('fantreffen.2026'))
            ->assertStatus(301)
            ->assertRedirect(route('veranstaltungen.show', $archivEvent));
    }

    public function test_guest_can_register_for_multiple_different_events_with_same_email(): void
    {
        Mail::fake();

        $erstesEvent = Veranstaltung::query()->where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
        $zweitesEvent = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();

        $erstesEvent->update(['anmeldung_aktiv' => true]);
        $zweitesEvent->update(['anmeldung_aktiv' => true]);

        $payload = [
            'vorname' => 'Alex',
            'nachname' => 'Archiv',
            'email' => 'alex@example.com',
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ];

        $this->post(route('veranstaltungen.anmeldung.store', $erstesEvent->slug), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post(route('veranstaltungen.anmeldung.store', $zweitesEvent->slug), [
            ...$payload,
            '_form_token' => $this->validFormToken(),
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('fantreffen_anmeldungen', 2);
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'veranstaltung_id' => $erstesEvent->id,
            'email' => 'alex@example.com',
        ]);
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'veranstaltung_id' => $zweitesEvent->id,
            'email' => 'alex@example.com',
        ]);

        $this->assertSame(2, FantreffenAnmeldung::where('email', 'alex@example.com')->count());
    }

    #[TestWith([Role::Admin->value])]
    #[TestWith([Role::Vorstand->value])]
    public function test_management_user_with_other_active_team_sees_event_management_cta_on_public_event_page(string $roleValue): void
    {
        $user = $this->createManagementUserWithDifferentCurrentTeam(Role::from($roleValue));
        $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();

        $response = $this->withoutVite()->actingAs($user)->get(route('veranstaltungen.show', $veranstaltung));

        $response->assertOk();
        $response->assertSee(route('admin.veranstaltungen.edit', $veranstaltung));
        $response->assertSee(route('admin.veranstaltungen.anmeldungen', $veranstaltung));
    }
}