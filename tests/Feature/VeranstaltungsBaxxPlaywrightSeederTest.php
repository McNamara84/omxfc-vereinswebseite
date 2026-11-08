<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(VeranstaltungsBaxxPlaywrightSeederTestFixtures::class);

test('repeated setup reuses members and restores the complete fixture after archiving', function (): void {
    $event = $this->seedFixture();
    $memberIds = $event->anmeldungen()->orderBy('user_id')->pluck('user_id')->all();
    $memberCount = User::count();
    expect($this->seedFixture()->id)->toBe($event->id)
        ->and(User::count())->toBe($memberCount)
        ->and($event->anmeldungen()->count())->toBe(2);
    $this->archive($event);
    expect($event->baxxVergaben()->count())->toBe(1)
        ->and(BaxxEarningProgress::whereIn('user_id', $memberIds)->count())->toBe(1)
        ->and(Activity::whereIn('user_id', $memberIds)->where('action', 'like', 'baxx_milestone_reached_%')->count())->toBe(1);
    $oldRegistration = $event->anmeldungen()->firstOrFail();
    Activity::create(['user_id' => $oldRegistration->user_id, 'subject_type' => $oldRegistration::class, 'subject_id' => $oldRegistration->id, 'action' => 'fantreffen_registered']);
    $reset = $this->seedFixture();
    expect($reset->id)->toBe($event->id)
        ->and(User::count())->toBe($memberCount + 1);
    // Nur der Test-Admin kam hinzu.
    expect($reset->anmeldungen()->orderBy('user_id')->pluck('user_id')->all())->toBe($memberIds);
    expect($reset->status)->toBe('veroeffentlicht')
        ->and($reset->baxx_status)->toBe(VeranstaltungsBaxxStatus::Offen)
        ->and($reset->teilnahme_baxx)->toBe(10)
        ->and($reset->anmeldung_aktiv)->toBeTrue()
        ->and($reset->baxx_abgeschlossen_am)->toBeNull()
        ->and($reset->baxx_abgeschlossen_von)->toBeNull()
        ->and($reset->baxxVergaben()->count())->toBe(0);
    foreach ($reset->anmeldungen as $registration) {
        expect($registration->teilgenommen)->toBeFalse()
            ->and($registration->teilnahme_bestaetigt_am)->toBeNull()
            ->and($registration->teilnahme_bestaetigt_von)->toBeNull();
    }
    expect(BaxxEarningProgress::whereIn('user_id', $memberIds)->count())->toBe(0)
        ->and(Activity::whereIn('user_id', $memberIds)->count())->toBe(0);
    $this->archive($reset);
    expect($reset->baxxVergaben()->count())->toBe(1)
        ->and(Activity::whereIn('user_id', $memberIds)->where('action', 'baxx_milestone_reached_1')->count())->toBe(1);
});

test('resetting one browser worker does not change other fixtures', function (): void {
    $first = $this->seedFixture('chromium-0');
    $second = $this->seedFixture('firefox-1');
    $this->archive($first);
    $this->archive($second);
    $secondMembers = $second->anmeldungen()->pluck('user_id');
    $secondRegistrationIds = $second->anmeldungen()->orderBy('id')->pluck('id')->all();
    $secondCreditId = $second->baxxVergaben()->firstOrFail()->id;
    $this->seedFixture('chromium-0');
    expect($first->baxxVergaben()->count())->toBe(0)
        ->and($second->fresh()->baxx_status)->toBe(VeranstaltungsBaxxStatus::Abgeschlossen)
        ->and($second->fresh()->teilnahme_baxx)->toBe(23)
        ->and($second->anmeldungen()->orderBy('id')->pluck('id')->all())->toBe($secondRegistrationIds)
        ->and($second->baxxVergaben()->firstOrFail()->id)->toBe($secondCreditId)
        ->and(Activity::whereIn('user_id', $secondMembers)->where('action', 'baxx_milestone_reached_1')->count())->toBe(1)
        ->and(BaxxEarningProgress::whereIn('user_id', $secondMembers)->count())->toBe(1);
});

test('seeder rejects non test databases before mutating data', function (string $environment, string $connection, string $database): void {
    $originalEnvironment = $this->app->environment();
    $originalConfig = ['database.default' => config('database.default'), 'database.connections.sqlite.database' => config('database.connections.sqlite.database')];
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
})->with([['production', 'sqlite', ':memory:'], ['testing', 'mysql', ':memory:'], ['testing', 'sqlite', 'database.sqlite']]);

test('invalid fixture key is rejected', function (): void {
    expect(fn () => $this->seedFixture('../invalid'))->toThrow(\InvalidArgumentException::class);
});
