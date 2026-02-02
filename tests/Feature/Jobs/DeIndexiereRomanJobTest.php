<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeIndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DeIndexiereRomanJob::class)]
class DeIndexiereRomanJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $team = Team::membersTeam();
        $this->admin = User::factory()->create(['current_team_id' => $team->id]);
    }

    #[Test]
    public function job_setzt_status_auf_hochgeladen(): void
    {
        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);

        $searchService = $this->mock(KompendiumSearchService::class);
        $searchService->shouldReceive('removeFromIndex')
            ->once()
            ->with($roman->dateipfad);

        $job = new DeIndexiereRomanJob($roman);
        $job->handle($searchService);

        $roman->refresh();
        $this->assertEquals('hochgeladen', $roman->status);
        $this->assertNull($roman->indexiert_am);
    }

    #[Test]
    public function job_funktioniert_auch_wenn_index_nicht_existiert(): void
    {
        // Erstelle Roman ohne echten Index
        $roman = KompendiumRoman::create([
            'dateiname' => '002 - Ohne Index.txt',
            'dateipfad' => 'romane/maddrax/002 - Ohne Index.txt',
            'serie' => 'maddrax',
            'roman_nr' => 2,
            'titel' => 'Ohne Index',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);

        // Service-Mock - removeFromIndex kann aufgerufen werden ohne Fehler
        $searchService = $this->mock(KompendiumSearchService::class);
        $searchService->shouldReceive('removeFromIndex')
            ->once()
            ->with($roman->dateipfad);

        // Job sollte nicht fehlschlagen, auch wenn der Index nicht existiert
        $job = new DeIndexiereRomanJob($roman);
        $job->handle($searchService);

        $roman->refresh();
        $this->assertEquals('hochgeladen', $roman->status);
    }
}
