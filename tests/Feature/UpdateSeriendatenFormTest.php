<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateSeriendatenForm;
use App\Models\Team;
use App\Models\User;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateSeriendatenFormTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure a separate storage path with test data
        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
        config(['filesystems.disks.local.root' => $this->testStoragePath . '/app/private']);

        // Reset cached data in the service
        $ref = new \ReflectionClass(MaddraxDataService::class);
        $property = $ref->getProperty('data');
        $property->setAccessible(true);
        $property->setValue(null, null);

        // Minimal Maddrax data for the component
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
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_mount_populates_initial_state_and_lists(): void
    {
        $user = $this->actingMember();
        $user->forceFill([
            'einstiegsroman' => '1 - Roman1',
            'lesestand' => '1 - Roman1',
            'lieblingsroman' => '2 - Roman2',
            'lieblingsfigur' => 'Figur1',
            'lieblingsmutation' => 'Mutation1',
            'lieblingsschauplatz' => 'Ort1',
            'lieblingsautor' => 'Author1',
            'lieblingszyklus' => 'Z1',
            'lieblingsthema' => 'Thema1',
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test(UpdateSeriendatenForm::class)
            ->assertSee('Serienspezifische Daten');

        $component
            ->assertSet('state.einstiegsroman', '1 - Roman1')
            ->assertSet('state.lesestand', '1 - Roman1')
            ->assertSet('state.lieblingsroman', '2 - Roman2')
            ->assertSet('state.lieblingsfigur', 'Figur1')
            ->assertSet('state.lieblingsmutation', 'Mutation1')
            ->assertSet('state.lieblingsschauplatz', 'Ort1')
            ->assertSet('state.lieblingsautor', 'Author1')
            ->assertSet('state.lieblingszyklus', 'Z1')
            ->assertSet('state.lieblingsthema', 'Thema1')
            ->assertSet('autoren', ['Author1', 'Author2'])
            ->assertSet('zyklen', ['Z1', 'Z2'])
            ->assertSet('romane', ['1 - Roman1', '2 - Roman2'])
            ->assertSet('figuren', ['Figur1', 'Figur2'])
            ->assertSet('schauplaetze', ['Ort1', 'Ort2'])
            ->assertSet('schlagworte', ['Thema1', 'Thema2']);
    }

    public function test_seriendaten_can_be_updated(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        Livewire::test(UpdateSeriendatenForm::class)
            ->set('state', [
                'einstiegsroman' => '1 - Roman1',
                'lesestand' => '2 - Roman2',
                'lieblingsroman' => '2 - Roman2',
                'lieblingsfigur' => 'Figur2',
                'lieblingsmutation' => 'Mutation1',
                'lieblingsschauplatz' => 'Ort2',
                'lieblingsautor' => 'Author2',
                'lieblingszyklus' => 'Z2',
                'lieblingsthema' => 'Thema2',
            ])
            ->call('updateSeriendaten')
            ->assertDispatched('saved');

        $user->refresh();

        $this->assertSame('1 - Roman1', $user->einstiegsroman);
        $this->assertSame('2 - Roman2', $user->lesestand);
        $this->assertSame('2 - Roman2', $user->lieblingsroman);
        $this->assertSame('Figur2', $user->lieblingsfigur);
        $this->assertSame('Mutation1', $user->lieblingsmutation);
        $this->assertSame('Ort2', $user->lieblingsschauplatz);
        $this->assertSame('Author2', $user->lieblingsautor);
        $this->assertSame('Z2', $user->lieblingszyklus);
        $this->assertSame('Thema2', $user->lieblingsthema);
    }
}
