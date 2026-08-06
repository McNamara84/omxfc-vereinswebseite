<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Admin\KompendiumRomanArchiveDownloadController;
use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveService;
use App\Services\KompendiumRomanArchiveWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(KompendiumRomanArchiveDownloadController::class)]
#[CoversClass(KompendiumRomanArchiveService::class)]
class KompendiumRomanArchiveDownloadTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private User $admin;

    /** @var list<string> */
    private array $archivePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Log::spy();
        $this->admin = $this->createUserWithRole(Role::Admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivePaths as $archivePath) {
            File::delete($archivePath);
        }

        parent::tearDown();
    }

    public function test_admin_can_download_all_novels_grouped_by_readable_series_name(): void
    {
        $maddrax = $this->createRoman([
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'status' => 'hochgeladen',
        ]);
        $missionMars = $this->createRoman([
            'dateiname' => '002 - Der rote Planet.txt',
            'dateipfad' => 'romane/missionmars/002 - Der rote Planet.txt',
            'serie' => 'missionmars',
            'roman_nr' => 2,
            'titel' => 'Der rote Planet',
            'status' => 'indexiert',
        ]);
        $volkDerTiefe = $this->createRoman([
            'dateiname' => '003 - Rückkehr.txt',
            'dateipfad' => 'romane/volkdertiefe/003 - Rückkehr.txt',
            'serie' => 'volkdertiefe',
            'roman_nr' => 3,
            'titel' => 'Rückkehr',
            'status' => 'fehler',
        ]);
        $hardcover = $this->createRoman([
            'dateiname' => '004 - Sonderband.txt',
            'dateipfad' => 'romane/hardcovers/004 - Sonderband.txt',
            'serie' => 'hardcovers',
            'roman_nr' => 4,
            'titel' => 'Sonderband',
            'status' => 'indexierung_laeuft',
        ]);

        Storage::disk('private')->put($maddrax->dateipfad, 'Maddrax-Inhalt');
        Storage::disk('private')->put($missionMars->dateipfad, 'Mission-Mars-Inhalt');
        Storage::disk('private')->put($volkDerTiefe->dateipfad, 'Inhalt mit Umlauten: äöüß');
        Storage::disk('private')->put($hardcover->dateipfad, 'Hardcover-Inhalt');

        $this->travelTo('2026-08-05 14:30:12');
        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'));

        $response->assertOk()
            ->assertDownload('omxfc-kompendium-romane-2026-08-05-143012.zip')
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $binaryResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $binaryResponse);
        $this->assertTrue($binaryResponse->shouldDeleteFileAfterSend());

        $archivePath = $binaryResponse->getFile()->getPathname();
        $this->archivePaths[] = $archivePath;

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivePath));
        $this->assertSame(4, $zip->numFiles);
        $this->assertSame(
            'Maddrax-Inhalt',
            $zip->getFromName('Maddrax - Die dunkle Zukunft der Erde/001 - Der Gott aus dem Eis.txt'),
        );
        $this->assertSame(
            'Mission-Mars-Inhalt',
            $zip->getFromName('Mission Mars/002 - Der rote Planet.txt'),
        );
        $this->assertSame(
            'Inhalt mit Umlauten: äöüß',
            $zip->getFromName('Das Volk der Tiefe/003 - Rückkehr.txt'),
        );
        $this->assertSame('Hardcover-Inhalt', $zip->getFromName('Hardcovers/004 - Sonderband.txt'));
        $this->assertTrue($zip->close());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_download_archive(Role $role): void
    {
        $nonAdmin = $this->createUserWithRole($role);

        $this->actingAs($nonAdmin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            'Mitglied' => [Role::Mitglied],
            'Vorstand' => [Role::Vorstand],
            'Kassenwart' => [Role::Kassenwart],
            'Ehrenmitglied' => [Role::Ehrenmitglied],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('login'));
    }

    public function test_empty_database_aborts_export(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas('error', 'Es sind keine Romane für den ZIP-Export vorhanden.');
    }

    public function test_missing_source_file_aborts_entire_export(): void
    {
        $available = $this->createRoman();
        Storage::disk('private')->put($available->dateipfad, 'Vorhanden');

        $this->createRoman([
            'dateiname' => '002 - Fehlt.txt',
            'dateipfad' => 'romane/maddrax/002 - Fehlt.txt',
            'roman_nr' => 2,
            'titel' => 'Fehlt',
        ]);

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas('error', 'Die Datei "002 - Fehlt.txt" fehlt. Der Export wurde abgebrochen.');

        $this->assertNoTemporaryArchivesExist();
    }

    #[DataProvider('unsafeStoredLocations')]
    public function test_unsafe_stored_location_aborts_export(
        string $serie,
        string $dateiname,
        string $dateipfad,
    ): void {
        $roman = $this->createRoman([
            'serie' => $serie,
            'dateiname' => $dateiname,
            'dateipfad' => $dateipfad,
        ]);

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas(
                'error',
                "Die Dateiinformationen für Roman #{$roman->id} sind ungültig. Der Export wurde abgebrochen.",
            );

        $this->assertNoTemporaryArchivesExist();
    }

    public static function unsafeStoredLocations(): array
    {
        return [
            'unbekannte Serie' => [
                'unbekannt',
                '001 - Geheim.txt',
                'romane/unbekannt/001 - Geheim.txt',
            ],
            'Unterverzeichnis im Dateinamen' => [
                'maddrax',
                'archiv/001 - Geheim.txt',
                'romane/maddrax/archiv/001 - Geheim.txt',
            ],
            'Backslash im Dateinamen' => [
                'maddrax',
                'archiv\\001 - Geheim.txt',
                'romane/maddrax/archiv\\001 - Geheim.txt',
            ],
            'andere Dateiendung' => [
                'maddrax',
                '001 - Geheim.html',
                'romane/maddrax/001 - Geheim.html',
            ],
            'abweichender Dateipfad' => [
                'maddrax',
                '001 - Erwartet.txt',
                'romane/maddrax/001 - Andere Datei.txt',
            ],
            'Steuerzeichen' => [
                'maddrax',
                "001 - Geheim\n.txt",
                "romane/maddrax/001 - Geheim\n.txt",
            ],
        ];
    }

    public function test_directory_instead_of_source_file_aborts_export(): void
    {
        $roman = $this->createRoman();
        Storage::disk('private')->makeDirectory($roman->dateipfad);

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas(
                'error',
                'Die Datei "001 - Der Gott aus dem Eis.txt" kann nicht gelesen werden. Der Export wurde abgebrochen.',
            );

        $this->assertNoTemporaryArchivesExist();
    }

    public function test_writer_failure_removes_temporary_archive_and_is_logged(): void
    {
        $roman = $this->createRoman();
        Storage::disk('private')->put($roman->dateipfad, 'Inhalt');

        $this->mock(KompendiumRomanArchiveWriter::class, function ($mock): void {
            $mock->shouldReceive('write')
                ->once()
                ->andThrow(new RuntimeException('Interner technischer Fehler'));
        });

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas('error', 'Das ZIP-Archiv konnte nicht erstellt werden.');

        $this->assertNoTemporaryArchivesExist();
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Kompendium: ZIP-Export wurde abgebrochen.'
                && $context['user_id'] === $this->admin->id
                && $context['fehler'] === 'Das ZIP-Archiv konnte nicht erstellt werden.'
                && $context['exception'] instanceof KompendiumRomanArchiveException
                && $context['exception']->getPrevious() instanceof RuntimeException
                && $context['exception']->getPrevious()->getMessage() === 'Interner technischer Fehler');
    }

    public function test_empty_archive_created_by_writer_is_rejected_and_removed(): void
    {
        $roman = $this->createRoman();
        Storage::disk('private')->put($roman->dateipfad, 'Inhalt');

        $this->mock(KompendiumRomanArchiveWriter::class, function ($mock): void {
            $mock->shouldReceive('write')->once();
        });

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download-all'))
            ->assertRedirect(route('kompendium.admin'))
            ->assertSessionHas('error', 'Das erstellte ZIP-Archiv ist ungültig.');

        $this->assertNoTemporaryArchivesExist();
    }

    public function test_admin_dashboard_shows_unfiltered_archive_download_link(): void
    {
        $downloadUrl = route('kompendium.admin.romane.download-all');

        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin', [
                'filterSerie' => 'missionmars',
                'filterStatus' => 'indexiert',
                'suchbegriff' => 'Mars',
            ]));

        $response->assertOk()
            ->assertSee('data-testid="download-all-novels"', false)
            ->assertSee('href="'.$downloadUrl.'"', false);

        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*data-testid="download-all-novels")(?=[^>]*href="'.preg_quote($downloadUrl, '/').'")(?![^>]*wire:navigate)[^>]*>/',
            $response->getContent(),
        );
    }

    private function createRoman(array $attributes = []): KompendiumRoman
    {
        return KompendiumRoman::query()->create(array_merge([
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ], $attributes));
    }

    private function assertNoTemporaryArchivesExist(): void
    {
        $temporaryDirectory = Storage::disk('private')->path('temp/kompendium-exports');

        if (! is_dir($temporaryDirectory)) {
            $this->assertDirectoryDoesNotExist($temporaryDirectory);

            return;
        }

        $this->assertSame([], File::files($temporaryDirectory));
    }
}
