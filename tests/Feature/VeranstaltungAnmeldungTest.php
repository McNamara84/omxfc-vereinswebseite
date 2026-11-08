<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(VeranstaltungAnmeldungTestFixtures::class);

test('published event page is accessible via slug', function (): void {
    Config::set('app.testing_minimal_layout', true);
    $veranstaltung = Veranstaltung::create(['titel' => 'Jubiläumsfeier Band 700', 'slug' => 'test-event-dynamisch', 'status' => 'veroeffentlicht', 'untertitel' => 'Das nächste Community-Treffen', 'teaser' => 'Feiere mit uns Band 700.', 'datum_von' => '2026-11-14 18:00:00', 'ort_name' => 'Cinedom Köln', 'anmeldung_aktiv' => true]);
    $response = $this->withoutVite()->get(route('veranstaltungen.show', $veranstaltung->slug));
    $response->assertOk();
    $response->assertSee('Jubiläumsfeier Band 700');
    $response->assertSee('Feiere mit uns Band 700.');
});

test('legacy fantreffen route redirects permanently to canonical archiv event', function (): void {
    $archivEvent = Veranstaltung::query()->where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
    $this->get(route('fantreffen.2026'))->assertStatus(301)->assertRedirect(route('veranstaltungen.show', $archivEvent));
});

test('guest can register for multiple different events with same email', function (): void {
    Mail::fake();
    $erstesEvent = Veranstaltung::query()->where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
    $zweitesEvent = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $erstesEvent->update(['status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
    $zweitesEvent->update(['anmeldung_aktiv' => true]);
    $payload = ['vorname' => 'Alex', 'nachname' => 'Archiv', 'email' => 'alex@example.com', 'website' => '', '_form_token' => $this->validFormToken()];
    $this->post(route('veranstaltungen.anmeldung.store', $erstesEvent->slug), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('veranstaltungen.anmeldung.store', $zweitesEvent->slug), [...$payload, '_form_token' => $this->validFormToken()])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('fantreffen_anmeldungen', 2);
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['veranstaltung_id' => $erstesEvent->id, 'email' => 'alex@example.com']);
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['veranstaltung_id' => $zweitesEvent->id, 'email' => 'alex@example.com']);
    expect(FantreffenAnmeldung::where('email', 'alex@example.com')->count())->toBe(2);
});

test('service rechecks duplicate registration without additional side effects', function (bool $firstIsMember, bool $secondIsMember, bool $changeEmail): void {
    Mail::fake();
    $event = Veranstaltung::create(['titel' => 'Doppelanmeldung', 'slug' => 'doppelanmeldung', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
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
        expect($exception->errors())->toBe(['email' => [$firstIsMember && $secondIsMember ? 'Du bist bereits für diese Veranstaltung angemeldet.' : $service->validationMessages($event)['email.unique']]]);
    }
    expect($event->anmeldungen()->pluck('id')->all())->toBe([$first->id])
        ->and($first->fresh()->email)->toBe($data['email'])
        ->and(Activity::where('subject_type', FantreffenAnmeldung::class)->where('subject_id', $first->id)->where('action', 'fantreffen_registered')->count())->toBe(1);
    Mail::assertNothingOutgoing();
})->with([[false, false, false], [true, true, false], [true, true, true], [false, true, false], [true, false, false]]);

test('member can register for multiple events', function (): void {
    Mail::fake();
    $member = $this->createUserWithRole(Role::Mitglied);
    foreach (['erstes-event', 'zweites-event'] as $slug) {
        $event = Veranstaltung::create(['titel' => $slug, 'slug' => $slug, 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
        $this->actingAs($member)->post(route('veranstaltungen.anmeldung.store', $event), ['_form_token' => $this->validFormToken()])->assertRedirect()->assertSessionHasNoErrors();
        expect($event->anmeldungen()->where('user_id', $member->id)->count())->toBe(1);
    }
});

test('different guests can register for the same event', function (): void {
    Mail::fake();
    $event = Veranstaltung::create(['titel' => 'Gäste', 'slug' => 'gaeste', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
    foreach (['erster@example.com', 'zweiter@example.com'] as $email) {
        $this->post(route('veranstaltungen.anmeldung.store', $event), ['vorname' => 'Alex', 'nachname' => 'Gast', 'email' => $email, '_form_token' => $this->validFormToken()])->assertRedirect()->assertSessionHasNoErrors();
    }
    expect($event->anmeldungen()->whereNull('user_id')->count())->toBe(2);
});

test('management user with other active team sees event management cta on public event page', function (string $roleValue): void {
    $user = $this->createManagementUserWithDifferentCurrentTeam(Role::from($roleValue));
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $response = $this->withoutVite()->actingAs($user)->get(route('veranstaltungen.show', $veranstaltung));
    $response->assertOk();
    $response->assertSee(route('admin.veranstaltungen.edit', $veranstaltung));
    $response->assertSee(route('admin.veranstaltungen.anmeldungen', $veranstaltung));
})->with([[Role::Admin->value], [Role::Vorstand->value]]);
