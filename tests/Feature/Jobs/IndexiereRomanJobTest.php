<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\IndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(IndexiereRomanJobTestFixtures::class);

covers(IndexiereRomanJob::class);

beforeEach(function (): void {
    Storage::fake('private');
    config(['scout.driver' => 'null']);
    $team = Team::membersTeam();
    $this->admin = User::factory()->create(['current_team_id' => $team->id]);
});

test('job indexiert datei und setzt status', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Testinhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $job = new IndexiereRomanJob($roman);
    $job->handle();
    $roman->refresh();
    expect($roman->status)->toBe('indexiert')
        ->and($roman->indexiert_am)->not->toBeNull()
        ->and($roman->fehler_nachricht)->toBeNull();
});

test('failed setzt status auf fehler', function (): void {
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $job = new IndexiereRomanJob($roman);
    $job->failed(new RuntimeException('Index fehlgeschlagen'));
    $roman->refresh();
    expect($roman->status)->toBe('fehler')
        ->and($roman->fehler_nachricht)->toBe('Index fehlgeschlagen');
});

test('job verwendet laravel 13 queue attribute fuer tries und timeout', function (): void {
    $reflection = new ReflectionClass(IndexiereRomanJob::class);
    $triesAttributes = $reflection->getAttributes(Tries::class);
    $timeoutAttributes = $reflection->getAttributes(Timeout::class);
    expect($triesAttributes)->toHaveCount(1)
        ->and($timeoutAttributes)->toHaveCount(1);
    $tries = $triesAttributes[0]->newInstance();
    $timeout = $timeoutAttributes[0]->newInstance();
    expect($tries->tries)->toBe(3)
        ->and($timeout->timeout)->toBe(120);
});
