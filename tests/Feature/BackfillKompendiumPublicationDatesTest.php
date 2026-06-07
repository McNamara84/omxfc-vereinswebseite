<?php

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillKompendiumPublicationDatesTest extends TestCase
{
    use RefreshDatabase;

    private function bindKompendiumMetadata(array $seriesByKey): void
    {
        $maddraxDataService = $this->createStub(MaddraxDataService::class);
        $maddraxDataService
            ->method('getSeries')
            ->willReturnCallback(fn (string $key) => collect($seriesByKey[$key] ?? []));

        $this->app->instance(KompendiumService::class, new KompendiumService($maddraxDataService));
    }

    public function test_command_backfills_publication_dates_from_metadata(): void
    {
        $this->bindKompendiumMetadata([
            'maddrax' => [
                ['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16'],
            ],
        ]);

        $user = User::factory()->create();

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        $this->artisan('kompendium:backfill-publication-dates')
            ->expectsOutput('1 aktualisiert, 0 unveraendert, 0 ohne Datum.')
            ->assertSuccessful();

        $this->assertSame('1999-02-16', $roman->fresh()->erstveroeffentlicht_am?->toDateString());
    }

    public function test_command_does_not_overwrite_existing_publication_dates(): void
    {
        $this->bindKompendiumMetadata([
            'maddrax' => [
                ['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16'],
            ],
        ]);

        $user = User::factory()->create();

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'erstveroeffentlicht_am' => '2000-01-01',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        $this->artisan('kompendium:backfill-publication-dates')
            ->expectsOutput('0 aktualisiert, 1 unveraendert, 0 ohne Datum.')
            ->assertSuccessful();

        $this->assertSame('2000-01-01', $roman->fresh()->erstveroeffentlicht_am?->toDateString());
    }
}
