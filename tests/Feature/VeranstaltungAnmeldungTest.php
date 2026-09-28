<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class VeranstaltungAnmeldungTest extends TestCase
{
    use CreatesFantreffenFormToken;
    use CreatesUserWithRole;
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

        $erstesEvent->update(['status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
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

    #[TestWith([false, false, false])]
    #[TestWith([true, true, false])]
    #[TestWith([true, true, true])]
    #[TestWith([false, true, false])]
    #[TestWith([true, false, false])]
    public function test_service_rechecks_duplicate_registration_without_additional_side_effects(bool $firstIsMember, bool $secondIsMember, bool $changeEmail): void
    {
        Mail::fake();
        $event = Veranstaltung::create([
            'titel' => 'Doppelanmeldung', 'slug' => 'doppelanmeldung',
            'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true,
        ]);
        $member = $this->createUserWithRole(Role::Mitglied);
        $data = ['vorname' => 'Alex', 'nachname' => 'Gast', 'email' => $member->email];
        $service = app(FantreffenRegistrationService::class);
        $first = $service->register($data, $event, $firstIsMember ? $member : null);
        if ($changeEmail) {
            $member->update(['email' => 'neue-adresse@example.com']);
        }
        Mail::fake();

        try {
            // Bereits validierte Daten müssen auch bei einem direkten Service-Aufruf geprüft werden.
            $service->register($data, $event, $secondIsMember ? $member : null);
            $this->fail('Eine doppelte Anmeldung muss als Validierungsfehler abgewiesen werden.');
        } catch (ValidationException $exception) {
            $this->assertSame(['email' => [$firstIsMember && $secondIsMember
                ? 'Du bist bereits für diese Veranstaltung angemeldet.'
                : $service->validationMessages($event)['email.unique']]], $exception->errors());
        }

        $this->assertSame([$first->id], $event->anmeldungen()->pluck('id')->all());
        $this->assertSame($data['email'], $first->fresh()->email);
        $this->assertSame(1, Activity::where('subject_type', FantreffenAnmeldung::class)
            ->where('subject_id', $first->id)->where('action', 'fantreffen_registered')->count());
        Mail::assertNothingOutgoing();
    }

    public function test_member_can_register_for_multiple_events(): void
    {
        Mail::fake();
        $member = $this->createUserWithRole(Role::Mitglied);
        foreach (['erstes-event', 'zweites-event'] as $slug) {
            $event = Veranstaltung::create([
                'titel' => $slug, 'slug' => $slug, 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true,
            ]);
            $this->actingAs($member)->post(route('veranstaltungen.anmeldung.store', $event), [
                '_form_token' => $this->validFormToken(),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(1, $event->anmeldungen()->where('user_id', $member->id)->count());
        }
    }

    public function test_different_guests_can_register_for_the_same_event(): void
    {
        Mail::fake();
        $event = Veranstaltung::create([
            'titel' => 'Gäste', 'slug' => 'gaeste', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true,
        ]);
        foreach (['erster@example.com', 'zweiter@example.com'] as $email) {
            $this->post(route('veranstaltungen.anmeldung.store', $event), [
                'vorname' => 'Alex', 'nachname' => 'Gast', 'email' => $email,
                '_form_token' => $this->validFormToken(),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $event->anmeldungen()->whereNull('user_id')->count());
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
