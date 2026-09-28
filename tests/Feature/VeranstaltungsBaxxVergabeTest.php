<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Livewire\FantreffenAdminDashboard;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use App\Services\RewardService;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mary\View\Components\Table as MaryTable;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VeranstaltungsBaxxVergabeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing_minimal_layout' => true]);
    }

    private function member(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $user->teams()->attach($team->id, ['role' => $role->value]);

        return $user;
    }

    private function event(array $data = []): Veranstaltung
    {
        return Veranstaltung::create(array_merge([
            'titel' => 'Baxx-Testveranstaltung',
            'slug' => 'baxx-'.Str::uuid(),
            'status' => 'veroeffentlicht',
            'anmeldung_aktiv' => true,
        ], $data));
    }

    private function registration(Veranstaltung $event, ?User $member = null, bool $confirmed = false): FantreffenAnmeldung
    {
        $anmeldung = $event->anmeldungen()->create([
            'user_id' => $member?->id,
            'vorname' => $member?->vorname ?? 'Gast',
            'nachname' => $member?->nachname ?? 'Test',
            'email' => $member?->email ?? Str::uuid().'@example.com',
            'ist_mitglied' => $member !== null,
            'payment_status' => 'free',
            'payment_amount' => 0,
        ]);
        $anmeldung->forceFill(['teilgenommen' => $confirmed])->save();

        return $anmeldung;
    }

    private function archive(Veranstaltung $event, User $actor, array $data = []): Veranstaltung
    {
        return app(VeranstaltungsBaxxService::class)->speichern($event, array_merge(['status' => 'archiviert'], $data), $actor);
    }

    private function payload(Veranstaltung $event, array $data = []): array
    {
        return array_merge(['titel' => $event->titel, 'slug' => $event->slug, 'status' => $event->status], $data);
    }

    public function test_defaults_and_existing_archives_are_safe(): void
    {
        $event = $this->event()->refresh();
        $registration = $this->registration($event)->refresh();
        $this->assertSame(10, $event->teilnahme_baxx);
        $this->assertSame(VeranstaltungsBaxxStatus::Offen, $event->baxx_status);
        $this->assertFalse($registration->teilgenommen);
        $this->assertNull($registration->teilnahme_bestaetigt_am);
        $this->assertSame(VeranstaltungsBaxxStatus::BestandAusgeschlossen,
            Veranstaltung::where('slug', 'maddrax-fantreffen-2026')->firstOrFail()->baxx_status);
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    public function test_management_can_confirm_and_revoke_attendance_from_another_active_team(Role $role): void
    {
        $actor = $this->member($role);
        $otherTeam = Team::factory()->create();
        $actor->forceFill(['current_team_id' => $otherTeam->id])->save();
        $event = $this->event();
        $registration = $this->registration($event, $this->member());

        $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
        $component->call('setTeilnahme', $registration->id, true)->assertHasNoErrors();
        $registration->refresh();
        $this->assertTrue($registration->teilgenommen);
        $this->assertSame($actor->id, $registration->teilnahme_bestaetigt_von);
        $confirmedAt = $registration->teilnahme_bestaetigt_am;
        $this->travel(1)->minutes();
        $component->call('setTeilnahme', $registration->id, true)->assertHasNoErrors();
        $this->assertTrue($registration->fresh()->teilnahme_bestaetigt_am->equalTo($confirmedAt));
        $component->call('setTeilnahme', $registration->id, false)->assertHasNoErrors();
        $registration->refresh();
        $this->assertFalse($registration->teilgenommen);
        $this->assertNull($registration->teilnahme_bestaetigt_am);
        $this->assertNull($registration->teilnahme_bestaetigt_von);
        $this->assertSame(0, $event->baxxVergaben()->count());

        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 25]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(25, $event->fresh()->teilnahme_baxx);
    }

    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Ehrenmitglied])]
    #[TestWith([Role::Anwaerter])]
    #[TestWith([Role::Mitwirkender])]
    public function test_other_roles_cannot_use_new_write_actions(Role $role): void
    {
        $actor = $this->member($role);
        $event = $this->event();
        $registration = $this->registration($event, $this->member());
        Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->call('setTeilnahme', $registration->id, true)->assertForbidden();
        // Anwärter werden bereits von der vorgeschalteten Mitgliedschafts-Middleware abgewiesen.
        $expectedStatus = $role === Role::Anwaerter ? 302 : 403;
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => 20]))->assertStatus($expectedStatus);
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertStatus($expectedStatus);
        $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), ['titel' => 'Archiv', 'slug' => 'neues-archiv', 'status' => 'archiviert'])->assertStatus($expectedStatus);
        $this->assertFalse($registration->fresh()->teilgenommen);
        $this->assertSame('veroeffentlicht', $event->fresh()->status);
        $this->assertSame(10, $event->fresh()->teilnahme_baxx);
        $this->assertSame(0, $event->baxxVergaben()->count());
    }

    public function test_admin_role_only_in_another_team_does_not_authorize_baxx_actions(): void
    {
        $actor = $this->member();
        $otherTeam = Team::factory()->create();
        $actor->teams()->attach($otherTeam->id, ['role' => Role::Admin->value]);
        $actor->forceFill(['current_team_id' => $otherTeam->id])->save();
        $event = $this->event();
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertForbidden();
    }

    public function test_guests_cannot_confirm_or_archive(): void
    {
        $event = $this->event();
        $registration = $this->registration($event, $this->member());
        Livewire::test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->call('setTeilnahme', $registration->id, true)->assertForbidden();
        $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))->assertRedirect(route('login'));
    }

    #[TestWith([2])]
    #[TestWith([10])]
    #[TestWith([50])]
    public function test_valid_amounts_can_be_created_and_updated(int $amount): void
    {
        $actor = $this->member(Role::Admin);
        $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), [
            'titel' => 'Neu', 'slug' => 'baxx-neu', 'status' => 'entwurf', 'teilnahme_baxx' => $amount,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $event = Veranstaltung::where('slug', 'baxx-neu')->firstOrFail();
        $this->assertSame($amount, $event->teilnahme_baxx);
        $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['teilnahme_baxx' => $amount]))->assertSessionHasNoErrors();
        $this->assertSame(0, $event->baxxVergaben()->count());
    }

    #[TestWith([1])]
    #[TestWith([51])]
    #[TestWith([-1])]
    #[TestWith([2.5])]
    #[TestWith(['abc'])]
    #[TestWith([''])]
    #[TestWith([null])]
    public function test_invalid_amounts_cannot_be_saved_or_paid(mixed $amount): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $this->registration($event, $this->member(), true);
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, [
            'teilnahme_baxx' => $amount, 'status' => 'archiviert',
        ]))->assertSessionHasErrors('teilnahme_baxx');
        $this->post(route('admin.veranstaltungen.store'), [
            'titel' => 'Ungültig', 'slug' => 'ungueltig', 'status' => 'entwurf', 'teilnahme_baxx' => $amount,
        ])->assertSessionHasErrors('teilnahme_baxx');
        $this->assertSame('veroeffentlicht', $event->fresh()->status);
        $this->assertSame(0, $event->baxxVergaben()->count());
    }

    public function test_cashier_preserves_amount_when_editing_other_fields_including_archived_events(): void
    {
        $actor = $this->member(Role::Kassenwart);
        $event = $this->event(['teilnahme_baxx' => 35]);
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['titel' => 'Korrigiert']))->assertSessionHasNoErrors();
        $event = $this->archive($event, $this->member(Role::Admin));
        $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['titel' => 'Archiv korrigiert']))->assertSessionHasNoErrors();
        $this->assertSame(35, $event->fresh()->teilnahme_baxx);
        $this->assertSame('Archiv korrigiert', $event->fresh()->titel);
        $this->get(route('admin.veranstaltungen.edit', $event))->assertOk()->assertDontSee('name="teilnahme_baxx"', false);
    }

    #[TestWith([Role::Mitglied, true])]
    #[TestWith([Role::Ehrenmitglied, true])]
    #[TestWith([Role::Kassenwart, true])]
    #[TestWith([Role::Vorstand, true])]
    #[TestWith([Role::Admin, true])]
    #[TestWith([Role::Anwaerter, false])]
    #[TestWith([Role::Mitwirkender, false])]
    public function test_current_membership_determines_recipients(Role $role, bool $eligible): void
    {
        $actor = $this->member(Role::Vorstand);
        $member = $this->member($role);
        $member->forceFill(['current_team_id' => Team::factory()->create()->id])->save();
        $event = $this->event(['teilnahme_baxx' => 23]);
        $registration = $this->registration($event, $member, true);
        // Das historische Flag bestimmt die Berechtigung ausdrücklich nicht.
        $registration->update(['ist_mitglied' => ! $eligible, 'orga_team' => true, 'payment_status' => 'pending']);
        $this->archive($event, $actor);
        $this->assertSame($eligible ? 1 : 0, $event->baxxVergaben()->count());
        $this->assertSame($eligible ? 23 : 0, app(RewardService::class)->getEarnedBaxx($member));
        if ($eligible) {
            $point = $event->baxxVergaben()->firstOrFail();
            $this->assertSame(Team::membersTeam()->id, $point->team_id);
            $this->assertNull($point->todo_id);
            $this->assertTrue($point->veranstaltung->is($event));
        }
    }

    public function test_confirmation_requires_a_linked_eligible_member_and_real_boolean(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $guest = $this->registration($event);
        $applicant = $this->registration($event, $this->member(Role::Anwaerter));
        $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event]);
        $component->call('setTeilnahme', $guest->id, true)->assertHasErrors('teilnahme');
        $component->call('setTeilnahme', $applicant->id, true)->assertHasErrors('teilnahme');
        $component->call('setTeilnahme', $guest->id, 'yes')->assertHasErrors('teilgenommen');
        $this->assertFalse($guest->fresh()->teilgenommen);
        $this->assertFalse($applicant->fresh()->teilgenommen);
        $memberRegistration = $this->registration($event, $this->member());
        $component->call('setTeilnahme', $memberRegistration->id, true)->assertHasNoErrors();
    }

    #[TestWith(['setTeilnahme'])]
    #[TestWith(['deleteAnmeldung'])]
    public function test_foreign_registration_cannot_be_confirmed_or_deleted(string $action): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $foreign = $this->registration($this->event(), $this->member());
        Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->call($action, ...($action === 'setTeilnahme' ? [$foreign->id, true] : [$foreign->id]))
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
        $this->assertFalse($foreign->fresh()->teilgenommen);
    }

    public function test_role_changes_and_departures_are_rechecked_when_archiving(): void
    {
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
        $this->assertSame(0, $event->baxxVergaben()->count());
        $this->assertSame(VeranstaltungsBaxxStatus::Abgeschlossen, $event->fresh()->baxx_status);
    }

    public function test_repeated_archiving_and_reopening_never_pay_twice(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $this->registration($event, $this->member(), true);
        $first = $this->archive($event, $actor, ['teilnahme_baxx' => 50]);
        $this->assertTrue($first->wasChanged('baxx_status'));
        $closedAt = $first->baxx_abgeschlossen_am;
        $repeated = $this->archive($event, $actor); // bewusst veraltetes Model
        $this->assertFalse($repeated->wasChanged('baxx_status'));
        $this->travel(1)->days();
        app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'veroeffentlicht'], $actor);
        $this->archive($event, $actor);
        $this->assertSame(1, $event->baxxVergaben()->count());
        $this->assertSame(50, (int) $event->baxxVergaben()->sum('points'));
        $this->assertTrue($event->fresh()->baxx_abgeschlossen_am->equalTo($closedAt));
        $this->assertSame($actor->id, $event->fresh()->baxx_abgeschlossen_von);
    }

    public function test_completion_summary_is_only_reported_for_the_request_that_completes_allocation(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $this->registration($event, $this->member(), true);
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, ['status' => 'archiviert']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Veranstaltung erfolgreich aktualisiert. Baxx-Vergabe abgeschlossen: 1 Mitglieder, je 10 Baxx, insgesamt 10 Baxx.');

        foreach (['archiviert', 'archiviert', 'veroeffentlicht', 'archiviert'] as $status) {
            $this->put(route('admin.veranstaltungen.update', $event), $this->payload($event, [
                'status' => $status, 'titel' => 'Bearbeitete Veranstaltung',
            ]))->assertSessionHasNoErrors()->assertSessionHas('success', 'Veranstaltung erfolgreich aktualisiert.');
        }
        $this->assertSame(1, $event->baxxVergaben()->count());
    }

    #[TestWith([Role::Admin, true])]
    #[TestWith([Role::Vorstand, true])]
    #[TestWith([Role::Kassenwart, false])]
    public function test_attendance_permission_is_checked_once_per_render_not_per_registration(Role $role, bool $mayConfirm): void
    {
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
        $this->assertSame(1, $permissionChecks);
        $this->assertSame($mayConfirm ? 20 : 0, substr_count($component->html(), 'data-confirmed='));

        // Eine neue Anfrage muss die Berechtigung nach einem Rollenwechsel erneut prüfen.
        $actor->teams()->updateExistingPivot(Team::membersTeam()->id, ['role' => Role::Kassenwart->value]);
        $component->call('$refresh');
        $this->assertSame(2, $permissionChecks);
        $this->assertSame(0, substr_count($component->html(), 'data-confirmed='));
    }

    #[TestWith([Role::Admin, false])]
    #[TestWith([Role::Vorstand, false])]
    #[TestWith([Role::Kassenwart, false])]
    #[TestWith([Role::Admin, true])]
    #[TestWith([Role::Vorstand, true])]
    #[TestWith([Role::Kassenwart, true])]
    public function test_real_mary_table_scopes_render_attendance_and_actions(Role $role, bool $archived): void
    {
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

            $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
                ->assertOk()
                ->assertSee($registration->email)
                ->assertSee($guest->email);

            $this->assertSame($archived ? 0 : 1, $permissionChecks);
            $this->assertSame(! $archived && $role !== Role::Kassenwart ? 1 : 0, substr_count($component->html(), 'data-confirmed='));
            if ($archived) {
                $component->assertSee('17 Baxx vergeben')->assertSee('Keine Gutschrift')->assertSee('Gesperrt')
                    ->assertDontSee('wire:click="deleteAnmeldung(', false);
            } else {
                $component->assertSee('Kein Mitgliedskonto')
                    ->assertSee('wire:click="deleteAnmeldung('.$registration->id.')"', false)
                    ->assertSee('wire:click="deleteAnmeldung('.$guest->id.')"', false);
            }
        } finally {
            $this->app->instance('env', $originalEnvironment);
            Blade::component($originalTable, 'table');
            Blade::compile($viewPath);
        }
    }

    public function test_closed_attendance_amount_and_deletion_stay_locked_after_reopening(): void
    {
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
        $this->assertTrue($registration->fresh()->teilgenommen);
        $this->assertSame(10, $event->fresh()->teilnahme_baxx);
    }

    public function test_old_archives_remain_excluded_even_after_reopening(): void
    {
        $actor = $this->member(Role::Admin);
        $event = Veranstaltung::where('slug', 'maddrax-fantreffen-2026')->firstOrFail();
        $registration = $this->registration($event, $this->member(), true);
        $event = app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'veroeffentlicht'], $actor);
        Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->assertSee('bereits vor Einführung archiviert')
            ->call('setTeilnahme', $registration->id, false)->assertHasErrors('teilnahme');
        $this->archive($event, $actor);
        $this->assertSame(VeranstaltungsBaxxStatus::BestandAusgeschlossen, $event->fresh()->baxx_status);
        $this->assertNull($event->fresh()->baxx_abgeschlossen_am);
        $this->assertSame(0, $event->baxxVergaben()->count());
    }

    public function test_new_archived_event_and_empty_event_are_finalized_without_points(): void
    {
        $actor = $this->member(Role::Admin);
        $this->actingAs($actor)->post(route('admin.veranstaltungen.store'), [
            'titel' => 'Leer', 'slug' => 'leeres-archiv', 'status' => 'archiviert',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '0 Mitglieder'));
        $event = Veranstaltung::where('slug', 'leeres-archiv')->firstOrFail();
        $this->assertSame(VeranstaltungsBaxxStatus::Abgeschlossen, $event->baxx_status);
        $this->assertNotNull($event->baxx_abgeschlossen_am);
        $this->assertSame(VeranstaltungsBaxxStatus::Abgeschlossen, $this->archive($this->event(), $actor)->baxx_status);
    }

    public function test_summary_filters_csv_and_pagination_do_not_limit_archiving(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        for ($i = 0; $i < 21; $i++) {
            $this->registration($event, $this->member(), true);
        }
        $notConfirmed = $this->registration($event, $this->member());
        $otherEvent = $this->event();
        $this->registration($otherEvent, $this->member(), true);
        $component = Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->assertSee('21 Mitglieder, insgesamt 210 Baxx')
            ->set('filterTeilnahme', 'offen')->assertSee($notConfirmed->email)
            ->set('search', 'findet-niemanden')->assertSee('21 Mitglieder, insgesamt 210 Baxx');
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, [
            'status' => 'archiviert', 'teilnahme_baxx' => 2,
        ]))->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '21 Mitglieder, je 2 Baxx, insgesamt 42 Baxx'));
        $this->assertSame(21, $event->baxxVergaben()->count());
        $this->assertSame(0, $otherEvent->baxxVergaben()->count());
        $component->set('filterTeilnahme', 'bestaetigt')->set('search', '')
            ->assertDontSee($notConfirmed->email)->assertSee('21 Mitglieder, insgesamt 42 Baxx');
    }

    public function test_csv_contains_actual_credit_only_and_keeps_history_after_role_change(): void
    {
        $actor = $this->member(Role::Admin);
        $member = $this->member();
        $event = $this->event(['teilnahme_baxx' => 17]);
        $registration = $this->registration($event, $member);
        app(VeranstaltungsBaxxService::class)->setTeilnahme($event, $registration->id, true, $actor);
        $this->actingAs($actor);
        $before = $this->csv($event);
        $this->assertSame('0', $before[1][14]);
        $this->assertSame('', $before[1][15]);
        $this->archive($event, $actor);
        $member->teams()->detach(Team::membersTeam()->id);
        $after = $this->csv($event);
        $this->assertSame('Teilnahme bestätigt', $after[0][12]);
        $this->assertSame('Ja', $after[1][12]);
        $this->assertNotEmpty($after[1][13]);
        $this->assertSame('17', $after[1][14]);
        $this->assertNotEmpty($after[1][15]);
        Livewire::test(FantreffenAdminDashboard::class, ['veranstaltung' => $event->fresh()])
            ->assertSee('1 Mitglieder, insgesamt 17 Baxx')->assertSee('17 Baxx vergeben');
    }

    private function csv(Veranstaltung $event): array
    {
        $component = new FantreffenAdminDashboard;
        $component->mount($event->fresh());
        $response = $component->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        return array_map(fn ($row) => str_getcsv($row, ',', '"', ''), explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    }

    public function test_failure_during_payment_rolls_back_every_credit_and_event_change(): void
    {
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
            $this->assertSame('Simulierter Buchungsfehler', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }
        $fresh = $event->fresh();
        $this->assertSame('veroeffentlicht', $fresh->status);
        $this->assertSame(VeranstaltungsBaxxStatus::Offen, $fresh->baxx_status);
        $this->assertSame(10, $fresh->teilnahme_baxx);
        $this->assertSame($event->titel, $fresh->titel);
        $this->assertNull($fresh->baxx_abgeschlossen_am);
        $this->assertSame(0, $fresh->baxxVergaben()->count());
        $this->archive($event, $actor);
        $this->assertSame(2, $event->baxxVergaben()->count());
    }

    public function test_missing_members_team_prevents_finalization(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        Team::membersTeam()->update(['name' => 'Umbenannt']);
        Cache::flush();
        // Berechtigung für diesen Fehlerpfad isolieren; ohne Team greift sonst schon die Policy.
        Gate::before(fn () => true);
        $this->assertCount(0, app(VeranstaltungsBaxxService::class)->eligibleUserIds($event));
        try {
            $this->archive($event, $actor);
            $this->fail('Fehlendes Mitglieder-Team darf keinen Abschluss erzeugen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('veroeffentlicht', $event->fresh()->status);
        $this->assertNull($event->fresh()->baxx_abgeschlossen_am);
    }

    public function test_database_prevents_duplicate_credits_but_allows_other_sources_and_events(): void
    {
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
        $this->assertSame(26, app(RewardService::class)->getEarnedBaxx($member));
        $this->expectException(QueryException::class);
        DB::table('user_points')->insert($data + ['veranstaltung_id' => $event->id]);
    }

    public function test_stale_registration_request_cannot_register_after_archiving(): void
    {
        Mail::fake();
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $this->archive($event, $actor);
        $this->assertFalse($event->fresh()->isRegistrationOpen());
        try {
            app(FantreffenRegistrationService::class)->register([], $event, $this->member());
            $this->fail('Eine veraltete Anmeldung darf ein Archiv nicht verändern.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('error', $exception->errors());
        }
        $this->assertSame(0, $event->anmeldungen()->count());
        Mail::assertNothingSent();
    }

    public function test_completion_metadata_cannot_be_forged_through_event_form(): void
    {
        $actor = $this->member(Role::Admin);
        $event = $this->event();
        $this->actingAs($actor)->put(route('admin.veranstaltungen.update', $event), $this->payload($event, [
            'baxx_status' => 'abgeschlossen', 'baxx_abgeschlossen_am' => now()->toDateTimeString(), 'baxx_abgeschlossen_von' => $actor->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(VeranstaltungsBaxxStatus::Offen, $event->fresh()->baxx_status);
        $this->assertNull($event->fresh()->baxx_abgeschlossen_am);
    }

    public function test_archive_retains_existing_milestone_notifications_without_duplicates(): void
    {
        $actor = $this->member(Role::Admin);
        $member = $this->member();
        $event = $this->event(['teilnahme_baxx' => 25]);
        $this->registration($event, $member, true);
        $this->archive($event, $actor);
        $this->archive($event, $actor);
        foreach ([1, 25] as $milestone) {
            $this->assertSame(1, Activity::where('user_id', $member->id)->where('action', 'baxx_milestone_reached_'.$milestone)->count());
        }
    }

    public function test_member_who_left_can_have_existing_confirmation_removed_before_archive(): void
    {
        $actor = $this->member(Role::Admin);
        $member = $this->member();
        $event = $this->event();
        $registration = $this->registration($event, $member, true);
        $member->teams()->detach(Team::membersTeam()->id);
        Livewire::actingAs($actor)->test(FantreffenAdminDashboard::class, ['veranstaltung' => $event])
            ->assertSee('1 bestätigte Teilnehmer sind aktuell nicht Baxx-berechtigt.')
            ->call('setTeilnahme', $registration->id, false)->assertHasNoErrors();
        $this->assertFalse($registration->fresh()->teilgenommen);
    }

    public function test_archived_event_rejects_public_registration_even_with_registration_enabled(): void
    {
        $event = $this->event(['status' => 'archiviert']);
        $this->post(route('veranstaltungen.anmeldung.store', $event), [
            'vorname' => 'Gast', 'nachname' => 'Test', 'email' => 'archiv-gast@example.com',
        ])->assertSessionHasErrors('error');
        $this->assertSame(0, $event->anmeldungen()->count());
    }

    public function test_create_form_exposes_default_and_bounds_and_cashier_cannot_choose_archive(): void
    {
        $this->actingAs($this->member(Role::Admin))->get(route('admin.veranstaltungen.create'))
            ->assertOk()->assertSee('name="teilnahme_baxx"', false)->assertSee('min="2" max="50"', false)->assertSee('value="10"', false);
        $this->actingAs($this->member(Role::Kassenwart))->get(route('admin.veranstaltungen.create'))
            ->assertOk()->assertDontSee('name="teilnahme_baxx"', false)->assertDontSee('<option value="archiviert"', false);
    }
}
