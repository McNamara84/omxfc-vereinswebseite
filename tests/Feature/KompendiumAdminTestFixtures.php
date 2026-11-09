<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\KompendiumAdminDashboard;
use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

trait KompendiumAdminTestFixtures
{
    use RefreshDatabase;

    private User $admin;

    private User $normalUser;

    private function bindKompendiumMetadata(array $seriesByKey): void
    {
        $maddraxDataService = $this->createStub(MaddraxDataService::class);
        $maddraxDataService->method('getSeries')->willReturnCallback(fn (string $key) => collect($seriesByKey[$key] ?? []));
        $this->app->instance(KompendiumService::class, new KompendiumService($maddraxDataService));
    }

    private function assertSpeichernNutztMetadatenFallbackBeiGeleertemDatum(?string $datum): void
    {
        $this->bindKompendiumMetadata(['maddrax' => [['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16']]]);
        Storage::disk('private')->put('romane/maddrax/001 - Der Gott aus dem Eis.txt', 'Inhalt');
        $roman = KompendiumRoman::create(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'erstveroeffentlicht_am' => '2000-01-01', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
        Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editErstveroeffentlichtAm', $datum)->call('speichern')->assertHasNoErrors()->assertSet('showEditModal', false);
        expect($roman->fresh()->erstveroeffentlicht_am?->toDateString())->toBe('1999-02-16');
    }
}
