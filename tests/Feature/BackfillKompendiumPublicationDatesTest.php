<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BackfillKompendiumPublicationDatesTestFixtures::class);

test('command backfills publication dates from metadata', function (): void {
    $this->bindKompendiumMetadata(['maddrax' => [['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16']]]);
    $user = User::factory()->create();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    $this->artisan('kompendium:backfill-publication-dates')->expectsOutput('1 aktualisiert, 0 unveraendert, 0 ohne Datum.')->assertSuccessful();
    expect($roman->fresh()->erstveroeffentlicht_am?->toDateString())->toBe('1999-02-16');
});

test('command updates timestamp when backfilling publication date', function (): void {
    $this->bindKompendiumMetadata(['maddrax' => [['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16']]]);
    $user = User::factory()->create();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    DB::table('kompendium_romane')->where('id', $roman->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    Carbon::setTestNow($now = Carbon::parse('2026-06-07 12:34:56'));
    try {
        $this->artisan('kompendium:backfill-publication-dates')->expectsOutput('1 aktualisiert, 0 unveraendert, 0 ohne Datum.')->assertSuccessful();
    } finally {
        Carbon::setTestNow();
    }
    $roman->refresh();
    expect($roman->erstveroeffentlicht_am?->toDateString())->toBe('1999-02-16')
        ->and($roman->updated_at?->toDateTimeString())->toBe($now->toDateTimeString());
});

test('command does not overwrite existing publication dates', function (): void {
    $this->bindKompendiumMetadata(['maddrax' => [['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16']]]);
    $user = User::factory()->create();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'erstveroeffentlicht_am' => '2000-01-01', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    $this->artisan('kompendium:backfill-publication-dates')->expectsOutput('0 aktualisiert, 1 unveraendert, 0 ohne Datum.')->assertSuccessful();
    expect($roman->fresh()->erstveroeffentlicht_am?->toDateString())->toBe('2000-01-01');
});
