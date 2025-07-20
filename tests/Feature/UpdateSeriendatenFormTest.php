<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Services\MaddraxDataService;
use Livewire\Livewire;
use Illuminate\Support\Facades\File;

class UpdateSeriendatenFormTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
        config(['filesystems.disks.local.root' => $this->testStoragePath . '/app/private']);

        $ref = new \ReflectionClass(MaddraxDataService::class);
        $property = $ref->getProperty('data');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $data = [
            [
                'nummer' => 1,
                'zyklus' => 'Z1',
                'titel' => 'Roman1',
                'text' => ['Author1'],
                'personen' => ['Figur1'],
                'orte' => ['Ort1'],
            ]
        ];
        File::put($this->testStoragePath . '/app/private/maddrax.json', json_encode($data));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        parent::tearDown();
    }

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }
}
