<?php

namespace Tests\Feature\Jobs;

use App\Jobs\IndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(IndexiereRomanJob::class)]
class IndexiereRomanJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config(['scout.driver' => 'null']);

        $team = Team::membersTeam();
        $this->admin = User::factory()->create(['current_team_id' => $team->id]);
    }

    #[Test]
    public function job_indexiert_datei_und_setzt_status(): void
    {
        Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Testinhalt');

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        $job = new IndexiereRomanJob($roman);
        $job->handle();

        $roman->refresh();

        $this->assertSame('indexiert', $roman->status);
        $this->assertNotNull($roman->indexiert_am);
        $this->assertNull($roman->fehler_nachricht);
    }

    #[Test]
    public function failed_setzt_status_auf_fehler(): void
    {
        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        $job = new IndexiereRomanJob($roman);
        $job->failed(new RuntimeException('Index fehlgeschlagen'));

        $roman->refresh();

        $this->assertSame('fehler', $roman->status);
        $this->assertSame('Index fehlgeschlagen', $roman->fehler_nachricht);
    }

    #[Test]
    public function job_verwendet_laravel_13_queue_attribute_fuer_tries_und_timeout(): void
    {
        $reflection = new ReflectionClass(IndexiereRomanJob::class);

        $tries = $reflection->getAttributes(Tries::class)[0]?->newInstance();
        $timeout = $reflection->getAttributes(Timeout::class)[0]?->newInstance();

        $this->assertSame(3, $tries?->tries);
        $this->assertSame(120, $timeout?->timeout);
    }
}