<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\File;

class MaddraxDataServiceTest extends TestCase
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
                'text' => ['Author1', 'Author2'],
                'personen' => ['Figur1'],
                'orte' => ['Ort1'],
                'schlagworte' => ['Thema1'],
            ],
            [
                'nummer' => 2,
                'zyklus' => 'Z2',
                'titel' => 'Roman2',
                'text' => ['Author1'],
                'personen' => ['Figur2'],
                'orte' => ['Ort2'],
                'schlagworte' => ['Thema2'],
            ],
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
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

    public function test_service_extracts_information(): void
    {
        $this->actingAs($this->actingMember());

        $this->assertSame(['Author1', 'Author2'], MaddraxDataService::getAutoren());
        $this->assertSame(['Z1', 'Z2'], MaddraxDataService::getZyklen());
        $this->assertSame(['1 - Roman1', '2 - Roman2'], MaddraxDataService::getRomane());
        $this->assertSame(['Figur1', 'Figur2'], MaddraxDataService::getFiguren());
        $this->assertSame(['Ort1', 'Ort2'], MaddraxDataService::getSchauplaetze());
        $this->assertSame(['Thema1', 'Thema2'], MaddraxDataService::getSchlagworte());
    }

    public function test_load_data_returns_empty_when_file_is_missing(): void
    {
        File::delete($this->testStoragePath . '/app/private/maddrax.json');
        $this->actingAs($this->actingMember());

        $this->assertSame([], MaddraxDataService::loadData());
    }

    public function test_load_data_returns_empty_when_json_is_invalid(): void
    {
        File::put($this->testStoragePath . '/app/private/maddrax.json', '{invalid');
        $this->actingAs($this->actingMember());

        $this->assertSame([], MaddraxDataService::getAutoren());
    }
}
