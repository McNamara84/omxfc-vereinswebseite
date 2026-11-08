<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\DeIndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DeIndexiereRomanJobTestFixtures::class);

covers(DeIndexiereRomanJob::class);

beforeEach(function (): void {
    Storage::fake('private');
    $team = Team::membersTeam();
    $this->admin = User::factory()->create(['current_team_id' => $team->id]);
});

test('job setzt status auf hochgeladen', function (): void {
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    $searchService = $this->mock(KompendiumSearchService::class);
    $searchService->shouldReceive('removeFromIndex')->once()->with($roman->dateipfad);
    $job = new DeIndexiereRomanJob($roman);
    $job->handle($searchService);
    $roman->refresh();
    expect($roman->status)->toEqual('hochgeladen')
        ->and($roman->indexiert_am)->toBeNull();
});

test('job funktioniert auch wenn index nicht existiert', function (): void {
    // Erstelle Roman ohne echten Index
    $roman = KompendiumRoman::create(['dateiname' => '002 - Ohne Index.txt', 'dateipfad' => 'romane/maddrax/002 - Ohne Index.txt', 'serie' => 'maddrax', 'roman_nr' => 2, 'titel' => 'Ohne Index', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    // Service-Mock - removeFromIndex kann aufgerufen werden ohne Fehler
    $searchService = $this->mock(KompendiumSearchService::class);
    $searchService->shouldReceive('removeFromIndex')->once()->with($roman->dateipfad);
    // Job sollte nicht fehlschlagen, auch wenn der Index nicht existiert
    $job = new DeIndexiereRomanJob($roman);
    $job->handle($searchService);
    $roman->refresh();
    expect($roman->status)->toEqual('hochgeladen');
});

test('job verwendet laravel 13 queue attribute fuer tries und timeout', function (): void {
    $reflection = new ReflectionClass(DeIndexiereRomanJob::class);
    $triesAttributes = $reflection->getAttributes(Tries::class);
    $timeoutAttributes = $reflection->getAttributes(Timeout::class);
    expect($triesAttributes)->toHaveCount(1)
        ->and($timeoutAttributes)->toHaveCount(1);
    $tries = $triesAttributes[0]->newInstance();
    $timeout = $timeoutAttributes[0]->newInstance();
    expect($tries->tries)->toBe(3)
        ->and($timeout->timeout)->toBe(60);
});
