<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VeranstaltungsBaxxMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private bool $committed = false;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_veranstaltungen_test') {
            throw new \RuntimeException('Only the isolated omxfc_veranstaltungen_test database may be used.');
        }
        parent::setUp();
    }

    #[TestWith(['archive'])]
    #[TestWith(['attendance'])]
    #[TestWith(['amount'])]
    #[TestWith(['delete'])]
    #[TestWith(['register'])]
    #[TestWith(['demotion'])]
    public function test_competing_writes_wait_for_the_lock_and_use_the_committed_state(string $action): void
    {
        $team = Team::membersTeam();
        $actor = User::factory()->create(['current_team_id' => $team->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $newMember = User::factory()->create(['current_team_id' => $team->id]);
        foreach ([$actor, $member, $newMember] as $user) {
            $user->teams()->attach($team->id, ['role' => $user->is($actor) ? Role::Admin->value : Role::Mitglied->value]);
        }
        $event = Veranstaltung::create(['titel' => 'Parallel', 'slug' => 'parallel-baxx', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
        $registration = $event->anmeldungen()->create(['user_id' => $member->id, 'email' => $member->email, 'ist_mitglied' => true]);
        app(VeranstaltungsBaxxService::class)->setTeilnahme($event, $registration->id, true, $actor);
        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committed = true;

        DB::beginTransaction();
        if ($action === 'demotion') {
            DB::table('team_user')->where('team_id', $team->id)->where('user_id', $member->id)
                ->update(['role' => Role::Anwaerter->value]);
        } else {
            Veranstaltung::whereKey($event->id)->lockForUpdate()->firstOrFail();
        }

        $process = new Process([PHP_BINARY, base_path('tests/Support/MariaDbVeranstaltungsBaxxWorker.php'), json_encode([
            'action' => $action, 'actor' => $actor->id, 'event' => $event->id,
            'registration' => $registration->id, 'newMember' => $newMember->id,
        ], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
        try {
            $process->start();
            $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, "locking\n")), $process->getErrorOutput());
            usleep(200_000);
            $this->assertTrue($process->isRunning(), 'Der konkurrierende Schreibzugriff muss auf die Sperre warten. '.$process->getOutput());

            if ($action !== 'demotion') {
                app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'archiviert'], $actor);
            }
            DB::commit();
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            $lines = explode("\n", trim($process->getOutput()));
            $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(in_array($action, ['archive', 'demotion'], true), $result['ok']);
            $this->assertSame(VeranstaltungsBaxxStatus::Abgeschlossen, $event->fresh()->baxx_status);
            $this->assertSame($action === 'demotion' ? 0 : 1, $event->baxxVergaben()->count());
            $this->assertSame(10, $event->fresh()->teilnahme_baxx);
            $this->assertSame(1, $event->anmeldungen()->count());
            $this->assertTrue($registration->fresh()->teilgenommen);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process->stop();
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed) {
                $this->artisan('migrate:fresh')->assertExitCode(0);
            }
        } finally {
            parent::tearDown();
        }
    }
}
