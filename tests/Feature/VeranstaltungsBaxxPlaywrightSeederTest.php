<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\BaxxMilestoneActivityService;
use App\Services\VeranstaltungsBaxxService;
use Database\Seeders\VeranstaltungsBaxxPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VeranstaltungsBaxxPlaywrightSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(string $fixture = 'default'): Veranstaltung
    {
        app(VeranstaltungsBaxxPlaywrightSeeder::class)->run($fixture);

        return Veranstaltung::where('slug', 'baxx-browserpruefung'.($fixture === 'default' ? '' : '-'.$fixture))->firstOrFail();
    }

    private function archive(Veranstaltung $event): void
    {
        $team = Team::membersTeam();
        $actor = User::factory()->create(['current_team_id' => $team->id]);
        $actor->teams()->attach($team->id, ['role' => Role::Admin->value]);
        $service = app(VeranstaltungsBaxxService::class);
        $service->setTeilnahme($event, $event->anmeldungen()->orderBy('id')->firstOrFail()->id, true, $actor);
        $service->speichern($event, ['status' => 'archiviert', 'teilnahme_baxx' => 23], $actor);
        app(BaxxMilestoneActivityService::class)->recordForUserPoint($event->baxxVergaben()->firstOrFail()->id);
    }

    public function test_repeated_setup_reuses_members_and_restores_the_complete_fixture_after_archiving(): void
    {
        $event = $this->seedFixture();
        $memberIds = $event->anmeldungen()->orderBy('user_id')->pluck('user_id')->all();
        $memberCount = User::count();
        $this->assertSame($event->id, $this->seedFixture()->id);
        $this->assertSame($memberCount, User::count());
        $this->assertSame(2, $event->anmeldungen()->count());
        $this->archive($event);
        $this->assertSame(1, $event->baxxVergaben()->count());
        $this->assertSame(1, BaxxEarningProgress::whereIn('user_id', $memberIds)->count());
        $this->assertSame(1, Activity::whereIn('user_id', $memberIds)->where('action', 'like', 'baxx_milestone_reached_%')->count());
        $oldRegistration = $event->anmeldungen()->firstOrFail();
        Activity::create([
            'user_id' => $oldRegistration->user_id, 'subject_type' => $oldRegistration::class,
            'subject_id' => $oldRegistration->id, 'action' => 'fantreffen_registered',
        ]);

        $reset = $this->seedFixture();
        $this->assertSame($event->id, $reset->id);
        $this->assertSame($memberCount + 1, User::count()); // Nur der Test-Admin kam hinzu.
        $this->assertSame($memberIds, $reset->anmeldungen()->orderBy('user_id')->pluck('user_id')->all());
        $this->assertSame('veroeffentlicht', $reset->status);
        $this->assertSame(VeranstaltungsBaxxStatus::Offen, $reset->baxx_status);
        $this->assertSame(10, $reset->teilnahme_baxx);
        $this->assertTrue($reset->anmeldung_aktiv);
        $this->assertNull($reset->baxx_abgeschlossen_am);
        $this->assertNull($reset->baxx_abgeschlossen_von);
        $this->assertSame(0, $reset->baxxVergaben()->count());
        foreach ($reset->anmeldungen as $registration) {
            $this->assertFalse($registration->teilgenommen);
            $this->assertNull($registration->teilnahme_bestaetigt_am);
            $this->assertNull($registration->teilnahme_bestaetigt_von);
        }
        $this->assertSame(0, BaxxEarningProgress::whereIn('user_id', $memberIds)->count());
        $this->assertSame(0, Activity::whereIn('user_id', $memberIds)->count());
        $this->archive($reset);
        $this->assertSame(1, $reset->baxxVergaben()->count());
        $this->assertSame(1, Activity::whereIn('user_id', $memberIds)->where('action', 'baxx_milestone_reached_1')->count());
    }

    public function test_resetting_one_browser_worker_does_not_change_other_fixtures(): void
    {
        $first = $this->seedFixture('chromium-0');
        $second = $this->seedFixture('firefox-1');
        $this->archive($first);
        $this->archive($second);
        $secondMembers = $second->anmeldungen()->pluck('user_id');
        $secondRegistrationIds = $second->anmeldungen()->orderBy('id')->pluck('id')->all();
        $secondCreditId = $second->baxxVergaben()->firstOrFail()->id;

        $this->seedFixture('chromium-0');

        $this->assertSame(0, $first->baxxVergaben()->count());
        $this->assertSame(VeranstaltungsBaxxStatus::Abgeschlossen, $second->fresh()->baxx_status);
        $this->assertSame(23, $second->fresh()->teilnahme_baxx);
        $this->assertSame($secondRegistrationIds, $second->anmeldungen()->orderBy('id')->pluck('id')->all());
        $this->assertSame($secondCreditId, $second->baxxVergaben()->firstOrFail()->id);
        $this->assertSame(1, Activity::whereIn('user_id', $secondMembers)->where('action', 'baxx_milestone_reached_1')->count());
        $this->assertSame(1, BaxxEarningProgress::whereIn('user_id', $secondMembers)->count());
    }

    #[TestWith(['production', 'sqlite', ':memory:'])]
    #[TestWith(['testing', 'mysql', ':memory:'])]
    #[TestWith(['testing', 'sqlite', 'database.sqlite'])]
    public function test_seeder_rejects_non_test_databases_before_mutating_data(string $environment, string $connection, string $database): void
    {
        $originalEnvironment = $this->app->environment();
        $originalConfig = [
            'database.default' => config('database.default'),
            'database.connections.sqlite.database' => config('database.connections.sqlite.database'),
        ];
        $this->app->instance('env', $environment);
        config(['database.default' => $connection, 'database.connections.sqlite.database' => $database]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dieser Seeder benötigt die isolierte Playwright-Testdatenbank.');
        try {
            $this->seedFixture();
        } finally {
            // RefreshDatabase muss seine Transaktion auf derselben Verbindung beenden.
            $this->app->instance('env', $originalEnvironment);
            config($originalConfig);
        }
    }

    public function test_invalid_fixture_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->seedFixture('../invalid');
    }
}
