<?php

namespace Tests\Unit;

use App\Http\Controllers\KompendiumController;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use App\Services\TeamPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversMethod;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit-Tests für die Pfad-Validierung im KompendiumController.
 *
 * Diese Tests prüfen isValidRomanPath() gegen Path-Traversal-Angriffe.
 *
 * Hinweis: RefreshDatabase ist erforderlich, da Tests\TestCase automatisch
 * die DB seeded. Die Methode selbst nutzt keine Datenbank.
 */
#[CoversMethod(KompendiumController::class, 'isValidRomanPath')]
class KompendiumPathValidationTest extends TestCase
{
    use RefreshDatabase;

    private KompendiumController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Controller mit gemockten Dependencies erstellen
        $this->controller = new KompendiumController(
            $this->createMock(TeamPointService::class),
            $this->createMock(KompendiumService::class),
            $this->createMock(KompendiumSearchService::class)
        );
    }

    /**
     * Zugriff auf private Methode isValidRomanPath() via Reflection
     */
    private function isValidRomanPath(string $path): bool
    {
        $method = new ReflectionMethod(KompendiumController::class, 'isValidRomanPath');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $path);
    }

    /* --------------------------------------------------------------------- */
    /*  Gültige Pfade */
    /* --------------------------------------------------------------------- */

    public function test_accepts_valid_roman_path(): void
    {
        $this->assertTrue($this->isValidRomanPath('romane/maddrax/001 - Der Gott aus dem Eis.txt'));
    }

    public function test_accepts_path_with_nested_directories(): void
    {
        $this->assertTrue($this->isValidRomanPath('romane/maddrax/zyklus-1/001 - Titel.txt'));
    }

    public function test_accepts_path_with_periods_in_filename(): void
    {
        $this->assertTrue($this->isValidRomanPath('romane/maddrax/001 - Dr. Jones.txt'));
    }

    public function test_accepts_path_with_period_in_series_name(): void
    {
        // Ein Serienname wie "series.name" sollte erlaubt sein
        $this->assertTrue($this->isValidRomanPath('romane/series.name/001 - Titel.txt'));
    }

    /* --------------------------------------------------------------------- */
    /*  Path-Traversal-Angriffe */
    /* --------------------------------------------------------------------- */

    public function test_rejects_simple_parent_directory_traversal(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('../.env'));
    }

    public function test_rejects_traversal_at_path_start(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('romane/../../../.env'));
    }

    public function test_rejects_traversal_in_middle_of_path(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('romane/maddrax/../../../etc/passwd'));
    }

    public function test_rejects_current_directory_reference(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('./romane/maddrax/001 - Titel.txt'));
    }

    public function test_rejects_hidden_traversal_with_backslashes(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('romane\\..\\..\\..\\secret.txt'));
    }

    /* --------------------------------------------------------------------- */
    /*  Pfade außerhalb des erlaubten Verzeichnisses */
    /* --------------------------------------------------------------------- */

    public function test_rejects_path_not_starting_with_romane(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('andere/ordner/datei.txt'));
    }

    public function test_rejects_absolute_path(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('/etc/passwd'));
    }

    public function test_rejects_windows_absolute_path(): void
    {
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('C:\\Windows\\System32\\config'));
    }

    public function test_rejects_similar_but_not_exact_base_path(): void
    {
        // "romane_fake" startet nicht mit "romane/"
        Log::shouldReceive('warning')->once();
        $this->assertFalse($this->isValidRomanPath('romane_fake/maddrax/001.txt'));
    }
}
