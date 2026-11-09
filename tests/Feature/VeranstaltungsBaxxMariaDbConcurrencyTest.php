<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(VeranstaltungsBaxxMariaDbConcurrencyTestFixtures::class);

beforeEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'mysql') {
        $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
    }
    if (getenv('DB_DATABASE') !== 'omxfc_veranstaltungen_test') {
        throw new \RuntimeException('Only the isolated omxfc_veranstaltungen_test database may be used.');
    }
});

afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});

test('competing writes wait for the lock and use the committed state', function (string $action): void {
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
    expect(DB::transactionLevel())->toBe(1);
    DB::connection()->commit();
    $this->committed = true;
    DB::beginTransaction();
    if ($action === 'demotion') {
        DB::table('team_user')->where('team_id', $team->id)->where('user_id', $member->id)->update(['role' => Role::Anwaerter->value]);
    } else {
        Veranstaltung::whereKey($event->id)->lockForUpdate()->firstOrFail();
    }
    $process = new Process([PHP_BINARY, base_path('tests/Support/MariaDbVeranstaltungsBaxxWorker.php'), json_encode(['action' => $action, 'actor' => $actor->id, 'event' => $event->id, 'registration' => $registration->id, 'newMember' => $newMember->id], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
    try {
        $process->start();
        expect($process->waitUntil(fn ($type, $output) => str_contains($output, "locking\n")))->toBeTrue();
        usleep(200000);
        expect($process->isRunning())->toBeTrue();
        if ($action !== 'demotion') {
            app(VeranstaltungsBaxxService::class)->speichern($event, ['status' => 'archiviert'], $actor);
        }
        DB::commit();
        expect($process->wait())->toBe(0);
        $lines = explode("\n", trim($process->getOutput()));
        $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        expect($result['ok'])->toBe(in_array($action, ['archive', 'demotion'], true))
            ->and($event->fresh()->baxx_status)->toBe(VeranstaltungsBaxxStatus::Abgeschlossen)
            ->and($event->baxxVergaben()->count())->toBe($action === 'demotion' ? 0 : 1)
            ->and($event->fresh()->teilnahme_baxx)->toBe(10)
            ->and($event->anmeldungen()->count())->toBe(1)
            ->and($registration->fresh()->teilgenommen)->toBeTrue();
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $process->stop();
    }
})->with([['archive'], ['attendance'], ['amount'], ['delete'], ['register'], ['demotion']]);

test('parallel duplicate registrations return validation errors after waiting for the event lock', function (bool $guest): void {
    Mail::fake();
    $member = User::factory()->create();
    $event = Veranstaltung::create(['titel' => 'Parallele Anmeldung', 'slug' => 'parallele-anmeldung', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true]);
    $data = ['vorname' => 'Alex', 'nachname' => 'Parallel', 'email' => $member->email];
    DB::connection()->commit();
    $this->committed = true;
    DB::beginTransaction();
    Veranstaltung::whereKey($event->id)->lockForUpdate()->firstOrFail();
    $process = new Process([PHP_BINARY, base_path('tests/Support/MariaDbVeranstaltungsBaxxWorker.php'), json_encode(['action' => 'register', 'actor' => $member->id, 'event' => $event->id, 'newMember' => $member->id, 'guest' => $guest, 'data' => $data, 'prevalidateRegistration' => true], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
    try {
        $process->start();
        expect($process->waitUntil(fn ($type, $output) => str_contains($output, "locking\n")))->toBeTrue();
        usleep(200000);
        expect($process->isRunning())->toBeTrue();
        $service = app(FantreffenRegistrationService::class);
        $first = $service->register($data, $event, $guest ? null : $member);
        DB::commit();
        expect($process->wait())->toBe(0);
        $lines = explode("\n", trim($process->getOutput()));
        expect(end($lines))->toBeJson();
        $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        expect($result['ok'])->toBeFalse()
            ->and($result['errors'])->toBe(['email' => [$guest ? $service->validationMessages($event)['email.unique'] : 'Du bist bereits für diese Veranstaltung angemeldet.']])
            ->and($event->anmeldungen()->pluck('id')->all())->toBe([$first->id])
            ->and(Activity::where('subject_type', FantreffenAnmeldung::class)->where('subject_id', $first->id)->where('action', 'fantreffen_registered')->count())->toBe(1);
        Mail::assertQueuedCount(2);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $process->stop();
    }
})->with([[false], [true]]);
