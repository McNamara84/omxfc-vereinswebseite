<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Livewire\FantreffenAdminDashboard;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use App\Services\RewardService;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mary\View\Components\Table as MaryTable;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(VeranstaltungsBaxxVergabeTestFixtures::class);

beforeEach(function (): void {
    config(['app.testing_minimal_layout' => true]);
});

test('defaults and existing archives are safe', function (): void {
    $event = $this->event()->refresh();
    $registration = $this->registration($event)->refresh();
    expect($event->teilnahme_baxx)->toBe(10)
        ->and($event->baxx_status)->toBe(VeranstaltungsBaxxStatus::Offen)
        ->and($registration->teilgenommen)->toBeFalse()
        ->and($registration->teilnahme_bestaetigt_am)->toBeNull()
        ->and(Veranstaltung::where('slug', 'maddrax-fantreffen-2026')->firstOrFail()->baxx_status)->toBe(VeranstaltungsBaxxStatus::BestandAusgeschlossen);
});

test('management can confirm and revoke attendance from another active team', function (Role $role): void {
    $actor = $this->member($role);
    $otherTeam = Team::factory()->create();
    $actor->forceFill(['current_team_id' => $otherTeam->id])->save();
    $event = $this->event();
    $registration = $this->registration($event, $this->member());
    $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
    $component->call('setTeilnahme', $registration->id, true)->assertHasNoErrors();
    $registration->refresh();
    expect($registration->teilgenommen)->toBeTrue()
        ->and($registration->teilnahme_bestaetigt_von)->toBe($actor->id);
    $confirmedAt = $registration->teilnahme_bestaetigt_am;
    $this->travel(1)->minutes();
    $component->call('setTeilnahme', $registration->id, true)->assertHasNoErrors();
    expect($registration->fresh()->teilnahme_bestaetigt_am->equalTo($confirmedAt))->toBeTrue();
    $component->call('setTeilnahme', $registration->id, false)->assertHasNoErrors();
    $registration->refresh();
    expect($registration->teilgenommen)->toBeFalse()
        ->and($registration->teilnahme_bestaetigt_am)->toBeNull()
        ->and($registration->teilnahme_bestaetigt_von)->toBeNull()
        ->and($event->baxxVergaben()->count())->toBe(0);
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 25]))->assertSessionHasNoErrors()->assertRedirect();
    expect($event->fresh()->teilnahme_baxx)->toBe(25);
})->with([[Role::Admin], [Role::Vorstand]]);

test('other roles cannot use new write actions', function (Role $role): void {
    $actor = $this->member($role);
    $event = $this->event();
    $registration = $this->registration($event, $this->member());
    Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->call('setTeilnahme', $registration->id, true)->assertForbidden();
    // Anwärter werden bereits von der vorgeschalteten Mitgliedschafts-Middleware abgewiesen.
    $expectedStatus = $role === Role::Anwaerter ? 302 : 403;
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 20]))->assertStatus($expectedStatus);
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertStatus($expectedStatus);
    $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), ['titel' => 'Archiv', 'slug' => 'neues-archiv', 'status' => 'archiviert'])->assertStatus($expectedStatus);
    expect($registration->fresh()->teilgenommen)->toBeFalse()
        ->and($event->fresh()->status)->toBe('veroeffentlicht')
        ->and($event->fresh()->teilnahme_baxx)->toBe(10)
        ->and($event->baxxVergaben()->count())->toBe(0);
})->with([[Role::Kassenwart], [Role::Mitglied], [Role::Ehrenmitglied], [Role::Anwaerter], [Role::Mitwirkender]]);

test('admin role only in another team does not authorize baxx actions', function (): void {
    $actor = $this->member();
    $otherTeam = Team::factory()->create();
    $actor->teams()->attach($otherTeam->id, ['role' => Role::Admin->value]);
    $actor->forceFill(['current_team_id' => $otherTeam->id])->save();
    $event = $this->event();
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertForbidden();
});

test('guests cannot confirm or archive', function (): void {
    $event = $this->event();
    $registration = $this->registration($event, $this->member());
    Livewire::test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->call('setTeilnahme', $registration->id, true)->assertForbidden();
    $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertRedirect(route('login'));
});

test('valid amounts can be created and updated', function (int $amount): void {
    $actor = $this->member(Role::Admin);
    $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), ['titel' => 'Neu', 'slug' => 'baxx-neu', 'status' => 'entwurf', 'teilnahme_baxx' => $amount])->assertSessionHasNoErrors()->assertRedirect();
    $event = Veranstaltung::where('slug', 'baxx-neu')->firstOrFail();
    expect($event->teilnahme_baxx)->toBe($amount);
    $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => $amount]))->assertSessionHasNoErrors();
    expect($event->baxxVergaben()->count())->toBe(0);
})->with([[2], [10], [50]]);

test('invalid amounts cannot be saved or paid', function (mixed $amount): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->registration($event, $this->member(), true);
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => $amount, 'status' => 'archiviert']))->assertSessionHasErrors('teilnahme_baxx');
    $this->post(route('admin.veranstaltungen.store'), ['titel' => 'Ungültig', 'slug' => 'ungueltig', 'status' => 'entwurf', 'teilnahme_baxx' => $amount])->assertSessionHasErrors('teilnahme_baxx');
    expect($event->fresh()->status)->toBe('veroeffentlicht')
        ->and($event->baxxVergaben()->count())->toBe(0);
})->with([[1], [51], [-1], [2.5], ['abc'], [''], [null]]);

test('cashier preserves amount when editing other fields including archived events', function (): void {
    $actor = $this->member(Role::Kassenwart);
    $event = $this->event(['teilnahme_baxx' => 35]);
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['titel' => 'Korrigiert']))->assertSessionHasNoErrors();
    $event = $this->archive($event, $this->member(Role::Admin));
    $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['titel' => 'Archiv korrigiert']))->assertSessionHasNoErrors();
    expect($event->fresh()->teilnahme_baxx)->toBe(35)
        ->and($event->fresh()->titel)->toBe('Archiv korrigiert');
    $this->get(route('admin.veranstaltungen.edit', $event))->assertOk()->assertDontSee('name="teilnahme_baxx"', false);
});

test('current membership determines recipients', function (Role $role, bool $eligible): void {
    $actor = $this->member(Role::Vorstand);
    $member = $this->member($role);
    $member->forceFill(['current_team_id' => Team::factory()->create()->id])->save();
    $event = $this->event(['teilnahme_baxx' => 23]);
    $registration = $this->registration($event, $member, true);
    // Das historische Flag bestimmt die Berechtigung ausdrücklich nicht.
    $registration->update(['ist_mitglied' => ! $eligible, 'orga_team' => true, 'payment_status' => 'pending']);
    $this->archive($event, $actor);
    expect($event->baxxVergaben()->count())->toBe($eligible ? 1 : 0)
        ->and(app(RewardService::class)->getEarnedBaxx($member))->toBe($eligible ? 23 : 0);
    if ($eligible) {
        $point = $event->baxxVergaben()->firstOrFail();
        expect($point->team_id)->toBe(Team::membersTeam()->id)
            ->and($point->todo_id)->toBeNull()
            ->and($point->veranstaltung->is($event))->toBeTrue();
    }
})->with([[Role::Mitglied, true], [Role::Ehrenmitglied, true], [Role::Kassenwart, true], [Role::Vorstand, true], [Role::Admin, true], [Role::Anwaerter, false], [Role::Mitwirkender, false]]);

test('confirmation requires a linked eligible member and real boolean', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $guest = $this->registration($event);
    $applicant = $this->registration($event, $this->member(Role::Anwaerter));
    $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
    $component->call('setTeilnahme', $guest->id, true)->assertHasErrors('teilnahme');
    $component->call('setTeilnahme', $applicant->id, true)->assertHasErrors('teilnahme');
    $component->call('setTeilnahme', $guest->id, 'yes')->assertHasErrors('teilgenommen');
    expect($guest->fresh()->teilgenommen)->toBeFalse()
        ->and($applicant->fresh()->teilgenommen)->toBeFalse();
    $memberRegistration = $this->registration($event, $this->member());
    $component->call('setTeilnahme', $memberRegistration->id, true)->assertHasNoErrors();
});

test('foreign registration cannot be confirmed or deleted', function (string $action): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $foreign = $this->registration($this->event(), $this->member());
    Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->call($action, ...$action === 'setTeilnahme' ? [$foreign->id, true] : [$foreign->id])->assertNotFound();
    expect($foreign->fresh())->not->toBeNull()
        ->and($foreign->fresh()->teilgenommen)->toBeFalse();
})->with([['setTeilnahme'], ['deleteAnmeldung']]);

test('role changes and departures are rechecked when archiving', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $departed = $this->member();
    $applicant = $this->member();
    foreach ([$departed, $applicant] as $member) {
        $registration = $this->registration($event, $member);
        app(VeranstaltungsBaxxService::class)->setTeilnahme($event, $registration->id, true, $actor);
    }
    $departed->teams()->detach(Team::membersTeam()->id);
    $applicant->teams()->updateExistingPivot(Team::membersTeam()->id, ['role' => Role::Anwaerter->value]);
    $this->registration($event, null, true);
    $this->registration($event, $this->member(), false);
    $this->archive($event, $actor);
    expect($event->baxxVergaben()->count())->toBe(0)
        ->and($event->fresh()->baxx_status)->toBe(VeranstaltungsBaxxStatus::Abgeschlossen);
});

test('repeated archiving and reopening never pay twice', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->registration($event, $this->member(), true);
    $first = $this->archive($event, $actor, ['teilnahme_baxx' => 50]);
    expect($first->wasChanged('baxx_status'))->toBeTrue();
    $closedAt = $first->baxx_abgeschlossen_am;
    $repeated = $this->archive($event, $actor);
    // bewusst veraltetes Model
    expect($repeated->wasChanged('baxx_status'))->toBeFalse();
    $this->travel(1)->days();
    app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'veroeffentlicht'], $actor);
    $this->archive($event, $actor);
    expect($event->baxxVergaben()->count())->toBe(1)
        ->and((int) $event->baxxVergaben()->sum('points'))->toBe(50)
        ->and($event->fresh()->baxx_abgeschlossen_am->equalTo($closedAt))->toBeTrue()
        ->and($event->fresh()->baxx_abgeschlossen_von)->toBe($actor->id);
});

test('completion summary is only reported for the request that completes allocation', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->registration($event, $this->member(), true);
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertSessionHasNoErrors()->assertSessionHas('success', 'Veranstaltung erfolgreich aktualisiert. Baxx-Vergabe abgeschlossen: 1 Mitglieder, je 10 Baxx, insgesamt 10 Baxx.');
    foreach (['archiviert', 'archiviert', 'veroeffentlicht', 'archiviert'] as $status) {
        $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => $status, 'titel' => 'Bearbeitete Veranstaltung']))->assertSessionHasNoErrors()->assertSessionHas('success', 'Veranstaltung erfolgreich aktualisiert.');
    }
    expect($event->baxxVergaben()->count())->toBe(1);
});

test('attendance permission is checked once per render not per registration', function (Role $role, bool $mayConfirm): void {
    $actor = $this->member($role);
    $event = $this->event();
    for ($index = 0; $index < 20; $index++) {
        $this->registration($event, $this->member());
    }
    $permissionChecks = 0;
    Gate::after(function (User $user, string $ability) use (&$permissionChecks): void {
        if ($ability === 'confirmAttendance') {
            $permissionChecks++;
        }
    });
    $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
    expect($permissionChecks)->toBe(1)
        ->and(substr_count($component->html(), 'data-confirmed='))->toBe($mayConfirm ? 20 : 0);
    // Eine neue Anfrage muss die Berechtigung nach einem Rollenwechsel erneut prüfen.
    $actor->teams()->updateExistingPivot(Team::membersTeam()->id, ['role' => Role::Kassenwart->value]);
    $component->call('$refresh');
    expect($permissionChecks)->toBe(2)
        ->and(substr_count($component->html(), 'data-confirmed='))->toBe(0);
})->with([[Role::Admin, true], [Role::Vorstand, true], [Role::Kassenwart, false]]);

test('real mary table scopes render attendance and actions', function (Role $role, bool $archived): void {
    $actor = $this->member($role);
    $event = $this->event(['teilnahme_baxx' => 17]);
    $registration = $this->registration($event, $this->member(), true);
    $guest = $this->registration($event);
    if ($archived) {
        $this->archive($event, $this->member(Role::Admin));
    }
    $originalEnvironment = $this->app->environment();
    $originalTable = Blade::getClassComponentAliases()['table'];
    $viewPath = resource_path('views/livewire/fantreffen-admin-dashboard.blade.php');
    $permissionChecks = 0;
    Gate::after(function (User $user, string $ability) use (&$permissionChecks): void {
        if ($ability === 'confirmAttendance') {
            $permissionChecks++;
        }
    });
    try {
        // Den regulären Tabellenzweig mit echten MaryUI-Scopes statt der Testtabelle rendern.
        $this->app->instance('env', 'local');
        Blade::component(MaryTable::class, 'table');
        Blade::compile($viewPath);
        $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->assertOk()->assertSee($registration->email)->assertSee($guest->email);
        expect($permissionChecks)->toBe($archived ? 0 : 1)
            ->and(substr_count($component->html(), 'data-confirmed='))->toBe(! $archived && $role !== Role::Kassenwart ? 1 : 0);
        if ($archived) {
            $component->assertSee('17 Baxx vergeben')->assertSee('Keine Gutschrift')->assertSee('Gesperrt')->assertDontSee('wire:click="deleteAnmeldung(', false);
        } else {
            $component->assertSee('Kein Mitgliedskonto')->assertSee('wire:click="deleteAnmeldung('.$registration->id.')"', false)->assertSee('wire:click="deleteAnmeldung('.$guest->id.')"', false);
        }
    } finally {
        $this->app->instance('env', $originalEnvironment);
        Blade::component($originalTable, 'table');
        Blade::compile($viewPath);
    }
})->with([[Role::Admin, false], [Role::Vorstand, false], [Role::Kassenwart, false], [Role::Admin, true], [Role::Vorstand, true], [Role::Kassenwart, true]]);

test('closed attendance amount and deletion stay locked after reopening', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $registration = $this->registration($event, $this->member(), true);
    $this->archive($event, $actor);
    $event = app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'veroeffentlicht'], $actor);
    $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
    $component->call('setTeilnahme', $registration->id, false)->assertHasErrors('teilnahme');
    $component->call('deleteAnmeldung', $registration->id)->assertHasErrors('teilnahme');
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 20]))->assertSessionHasErrors('teilnahme_baxx');
    $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 10, 'titel' => 'Neuer Titel']))->assertSessionHasNoErrors();
    expect($registration->fresh()->teilgenommen)->toBeTrue()
        ->and($event->fresh()->teilnahme_baxx)->toBe(10);
});

test('old archives remain excluded even after reopening', function (): void {
    $actor = $this->member(Role::Admin);
    $event = Veranstaltung::where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
    $registration = $this->registration($event, $this->member(), true);
    $event = app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'veroeffentlicht'], $actor);
    Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->assertSee('bereits vor Einführung archiviert')->call('setTeilnahme', $registration->id, false)->assertHasErrors('teilnahme');
    $this->archive($event, $actor);
    expect($event->fresh()->baxx_status)->toBe(VeranstaltungsBaxxStatus::BestandAusgeschlossen)
        ->and($event->fresh()->baxx_abgeschlossen_am)->toBeNull()
        ->and($event->baxxVergaben()->count())->toBe(0);
});

test('new archived event and empty event are finalized without points', function (): void {
    $actor = $this->member(Role::Admin);
    $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), ['titel' => 'Leer', 'slug' => 'leeres-archiv', 'status' => 'archiviert'])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '0 Mitglieder'));
    $event = Veranstaltung::where('slug', 'leeres-archiv')->firstOrFail();
    expect($event->baxx_status)->toBe(VeranstaltungsBaxxStatus::Abgeschlossen)
        ->and($event->baxx_abgeschlossen_am)->not->toBeNull()
        ->and($this->archive($this->event(), $actor)->baxx_status)->toBe(VeranstaltungsBaxxStatus::Abgeschlossen);
});

test('summary filters csv and pagination do not limit archiving', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    for ($i = 0; $i < 21; $i++) {
        $this->registration($event, $this->member(), true);
    }
    $notConfirmed = $this->registration($event, $this->member());
    $otherEvent = $this->event();
    $this->registration($otherEvent, $this->member(), true);
    $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->assertSee('21 Mitglieder, insgesamt 210 Baxx')->set('filterTeilnahme', 'offen')->assertSee($notConfirmed->email)->set('search', 'findet-niemanden')->assertSee('21 Mitglieder, insgesamt 210 Baxx');
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert', 'teilnahme_baxx' => 2]))->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '21 Mitglieder, je 2 Baxx, insgesamt 42 Baxx'));
    expect($event->baxxVergaben()->count())->toBe(21)
        ->and($otherEvent->baxxVergaben()->count())->toBe(0);
    $component->set('filterTeilnahme', 'bestaetigt')->set('search', '')->assertDontSee($notConfirmed->email)->assertSee('21 Mitglieder, insgesamt 42 Baxx');
});

test('csv contains actual credit only and keeps history after role change', function (): void {
    $actor = $this->member(Role::Admin);
    $member = $this->member();
    $event = $this->event(['teilnahme_baxx' => 17]);
    $registration = $this->registration($event, $member);
    app(VeranstaltungsBaxxService::class)->setTeilnahme($event, $registration->id, true, $actor);
    $this->actingAs($actor);
    $before = $this->csv($event);
    expect($before[1][14])->toBe('0')
        ->and($before[1][15])->toBe('');
    $this->archive($event, $actor);
    $member->teams()->detach(Team::membersTeam()->id);
    $after = $this->csv($event);
    expect($after[0][12])->toBe('Teilnahme bestätigt')
        ->and($after[1][12])->toBe('Ja')
        ->and($after[1][13])->not->toBeEmpty()
        ->and($after[1][14])->toBe('17')
        ->and($after[1][15])->not->toBeEmpty();
    Livewire::test(FantreffenAdminDashboard::class, ['veranstaltung' => $event->fresh()])->assertSee('1 Mitglieder, insgesamt 17 Baxx')->assertSee('17 Baxx vergeben');
});

test('failure during payment rolls back every credit and event change', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->registration($event, $this->member(), true);
    $this->registration($event, $this->member(), true);
    $attempts = 0;
    $eventName = 'eloquent.creating: '.UserPoint::class;
    Event::listen($eventName, function (UserPoint $point) use (&$attempts, $event) {
        if ($point->veranstaltung_id === $event->id && ++$attempts === 2) {
            throw new \RuntimeException('Simulierter Buchungsfehler');
        }
    });
    try {
        $this->archive($event, $actor, ['titel' => 'Nicht speichern', 'teilnahme_baxx' => 20]);
        $this->fail('Der simulierte Fehler muss den Abschluss abbrechen.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulierter Buchungsfehler');
    } finally {
        Event::forget($eventName);
    }
    $fresh = $event->fresh();
    expect($fresh->status)->toBe('veroeffentlicht')
        ->and($fresh->baxx_status)->toBe(VeranstaltungsBaxxStatus::Offen)
        ->and($fresh->teilnahme_baxx)->toBe(10)
        ->and($fresh->titel)->toBe($event->titel)
        ->and($fresh->baxx_abgeschlossen_am)->toBeNull()
        ->and($fresh->baxxVergaben()->count())->toBe(0);
    $this->archive($event, $actor);
    expect($event->baxxVergaben()->count())->toBe(2);
});

test('missing members team prevents finalization', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    Team::membersTeam()->update(['name' => 'Umbenannt']);
    Cache::flush();
    // Berechtigung für diesen Fehlerpfad isolieren; ohne Team greift sonst schon die Policy.
    Gate::before(fn () => true);
    expect(app(VeranstaltungsBaxxService::class)->eligibleUserIds($event))->toHaveCount(0);
    try {
        $this->archive($event, $actor);
        $this->fail('Fehlendes Mitglieder-Team darf keinen Abschluss erzeugen.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }
    expect($event->fresh()->status)->toBe('veroeffentlicht')
        ->and($event->fresh()->baxx_abgeschlossen_am)->toBeNull();
});

test('database prevents duplicate credits but allows other sources and events', function (): void {
    $actor = $this->member(Role::Admin);
    $member = $this->member();
    $event = $this->event();
    $otherEvent = $this->event();
    $this->registration($event, $member, true);
    $this->registration($otherEvent, $member, true);
    $this->archive($event, $actor);
    $this->archive($otherEvent, $actor);
    $data = ['user_id' => $member->id, 'team_id' => Team::membersTeam()->id, 'points' => 3];
    UserPoint::create($data);
    UserPoint::create($data);
    expect(app(RewardService::class)->getEarnedBaxx($member))->toBe(26)
        ->and(fn () => DB::table('user_points')->insert($data + ['veranstaltung_id' => $event->id]))->toThrow(QueryException::class);
});

test('stale registration request cannot register after archiving', function (): void {
    Mail::fake();
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->archive($event, $actor);
    expect($event->fresh()->isRegistrationOpen())->toBeFalse();
    try {
        app(FantreffenRegistrationService::class)->register([], $event, $this->member());
        $this->fail('Eine veraltete Anmeldung darf ein Archiv nicht verändern.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('error');
    }
    expect($event->anmeldungen()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('completion metadata cannot be forged through event form', function (): void {
    $actor = $this->member(Role::Admin);
    $event = $this->event();
    $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['baxx_status' => 'abgeschlossen', 'baxx_abgeschlossen_am' => now()->toDateTimeString(), 'baxx_abgeschlossen_von' => $actor->id]))->assertSessionHasNoErrors();
    expect($event->fresh()->baxx_status)->toBe(VeranstaltungsBaxxStatus::Offen)
        ->and($event->fresh()->baxx_abgeschlossen_am)->toBeNull();
});

test('archive retains existing milestone notifications without duplicates', function (): void {
    $actor = $this->member(Role::Admin);
    $member = $this->member();
    $event = $this->event(['teilnahme_baxx' => 25]);
    $this->registration($event, $member, true);
    $this->archive($event, $actor);
    $this->archive($event, $actor);
    foreach ([1, 25] as $milestone) {
        expect(Activity::where('user_id', $member->id)->where('action', 'baxx_milestone_reached_'.$milestone)->count())->toBe(1);
    }
});

test('member who left can have existing confirmation removed before archive', function (): void {
    $actor = $this->member(Role::Admin);
    $member = $this->member();
    $event = $this->event();
    $registration = $this->registration($event, $member, true);
    $member->teams()->detach(Team::membersTeam()->id);
    Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])->assertSee('1 bestätigte Teilnehmer sind aktuell nicht Baxx-berechtigt.')->call('setTeilnahme', $registration->id, false)->assertHasNoErrors();
    expect($registration->fresh()->teilgenommen)->toBeFalse();
});

test('archived event rejects public registration even with registration enabled', function (): void {
    $event = $this->event(['status' => 'archiviert']);
    $this->post(route('veranstaltungen.anmeldung.store', $event), ['vorname' => 'Gast', 'nachname' => 'Test', 'email' => 'archiv-gast@example.com'])->assertSessionHasErrors('error');
    expect($event->anmeldungen()->count())->toBe(0);
});

test('create form exposes default and bounds and cashier cannot choose archive', function (): void {
    $this->actingAs($this->member(Role::Admin))->get(route('admin.veranstaltungen.create'))->assertOk()->assertSee('name="teilnahme_baxx"', false)->assertSee('min="2" max="50"', false)->assertSee('value="10"', false);
    $this->actingAs($this->member(Role::Kassenwart))->get(route('admin.veranstaltungen.create'))->assertOk()->assertDontSee('name="teilnahme_baxx"', false)->assertDontSee('<option value="archiviert"', false);
});
