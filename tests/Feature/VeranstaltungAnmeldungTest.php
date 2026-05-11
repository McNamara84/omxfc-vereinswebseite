<?php

namespace Tests\Feature;

use App\Models\FantreffenAnmeldung;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\TestCase;

class VeranstaltungAnmeldungTest extends TestCase
{
    use CreatesFantreffenFormToken;
    use RefreshDatabase;

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
}