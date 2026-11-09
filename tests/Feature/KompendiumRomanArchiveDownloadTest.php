<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Admin\KompendiumRomanArchiveDownloadController;
use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveService;
use App\Services\KompendiumRomanArchiveWriter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

pest()->extend(TestCase::class)->use(KompendiumRomanArchiveDownloadTestFixtures::class);
covers(KompendiumRomanArchiveDownloadController::class);
covers(KompendiumRomanArchiveService::class);
dataset('KompendiumRomanArchiveDownloadTest.nonAdminRoles', static fn (): array => ['Mitglied' => [Role::Mitglied], 'Vorstand' => [Role::Vorstand], 'Kassenwart' => [Role::Kassenwart], 'Ehrenmitglied' => [Role::Ehrenmitglied]]);
dataset('KompendiumRomanArchiveDownloadTest.unsafeStoredLocations', static fn (): array => ['unbekannte Serie' => ['unbekannt', '001 - Geheim.txt', 'romane/unbekannt/001 - Geheim.txt'], 'Unterverzeichnis im Dateinamen' => ['maddrax', 'archiv/001 - Geheim.txt', 'romane/maddrax/archiv/001 - Geheim.txt'], 'Backslash im Dateinamen' => ['maddrax', 'archiv\001 - Geheim.txt', 'romane/maddrax/archiv\001 - Geheim.txt'], 'andere Dateiendung' => ['maddrax', '001 - Geheim.html', 'romane/maddrax/001 - Geheim.html'], 'abweichender Dateipfad' => ['maddrax', '001 - Erwartet.txt', 'romane/maddrax/001 - Andere Datei.txt'], 'Steuerzeichen' => ['maddrax', "001 - Geheim\n.txt", "romane/maddrax/001 - Geheim\n.txt"]]);
beforeEach(function (): void {
    Storage::fake('private');
    Log::spy();
    $this->admin = $this->createUserWithRole(Role::Admin);
});
afterEach(function (): void {
    foreach ($this->archivePaths as $archivePath) {
        File::delete($archivePath);
    }
});
test('admin can download all novels grouped by readable series name', function (): void {
    $maddrax = $this->createRoman(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'status' => 'hochgeladen']);
    $missionMars = $this->createRoman(['dateiname' => '002 - Der rote Planet.txt', 'dateipfad' => 'romane/missionmars/002 - Der rote Planet.txt', 'serie' => 'missionmars', 'roman_nr' => 2, 'titel' => 'Der rote Planet', 'status' => 'indexiert']);
    $volkDerTiefe = $this->createRoman(['dateiname' => '003 - Rückkehr.txt', 'dateipfad' => 'romane/volkdertiefe/003 - Rückkehr.txt', 'serie' => 'volkdertiefe', 'roman_nr' => 3, 'titel' => 'Rückkehr', 'status' => 'fehler']);
    $hardcover = $this->createRoman(['dateiname' => '004 - Sonderband.txt', 'dateipfad' => 'romane/hardcovers/004 - Sonderband.txt', 'serie' => 'hardcovers', 'roman_nr' => 4, 'titel' => 'Sonderband', 'status' => 'indexierung_laeuft']);
    Storage::disk('private')->put($maddrax->dateipfad, 'Maddrax-Inhalt');
    Storage::disk('private')->put($missionMars->dateipfad, 'Mission-Mars-Inhalt');
    Storage::disk('private')->put($volkDerTiefe->dateipfad, 'Inhalt mit Umlauten: äöüß');
    Storage::disk('private')->put($hardcover->dateipfad, 'Hardcover-Inhalt');
    $this->travelTo('2026-08-05 14:30:12');
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'));
    $response->assertOk()->assertDownload('omxfc-kompendium-romane-2026-08-05-143012.zip')->assertHeader('Content-Type', 'application/zip')->assertHeader('X-Content-Type-Options', 'nosniff');
    $binaryResponse = $response->baseResponse;
    expect($binaryResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($binaryResponse->shouldDeleteFileAfterSend())->toBeTrue();
    $archivePath = $binaryResponse->getFile()->getPathname();
    $this->archivePaths[] = $archivePath;
    $zip = new ZipArchive;
    expect($zip->open($archivePath))->toBeTrue()
        ->and($zip->numFiles)->toBe(4)
        ->and($zip->getFromName('Maddrax - Die dunkle Zukunft der Erde/001 - Der Gott aus dem Eis.txt'))->toBe('Maddrax-Inhalt')
        ->and($zip->getFromName('Mission Mars/002 - Der rote Planet.txt'))->toBe('Mission-Mars-Inhalt')
        ->and($zip->getFromName('Das Volk der Tiefe/003 - Rückkehr.txt'))->toBe('Inhalt mit Umlauten: äöüß')
        ->and($zip->getFromName('Hardcovers/004 - Sonderband.txt'))->toBe('Hardcover-Inhalt')
        ->and($zip->close())->toBeTrue();
});
test('non admin cannot download archive', function (Role $role): void {
    $nonAdmin = $this->createUserWithRole($role);
    $this->actingAs($nonAdmin)->get(route('kompendium.admin.romane.download-all'))->assertForbidden();
})->with('KompendiumRomanArchiveDownloadTest.nonAdminRoles');
test('guest is redirected to login', function (): void {
    $this->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('login'));
});
test('empty database aborts export', function (): void {
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', 'Es sind keine Romane für den ZIP-Export vorhanden.');
});
test('missing source file aborts entire export', function (): void {
    $available = $this->createRoman();
    Storage::disk('private')->put($available->dateipfad, 'Vorhanden');
    $this->createRoman(['dateiname' => '002 - Fehlt.txt', 'dateipfad' => 'romane/maddrax/002 - Fehlt.txt', 'roman_nr' => 2, 'titel' => 'Fehlt']);
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', 'Die Datei "002 - Fehlt.txt" fehlt. Der Export wurde abgebrochen.');
    $this->assertNoTemporaryArchivesExist();
});
test('unsafe stored location aborts export', function (string $serie, string $dateiname, string $dateipfad): void {
    $roman = $this->createRoman(['serie' => $serie, 'dateiname' => $dateiname, 'dateipfad' => $dateipfad]);
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', "Die Dateiinformationen für Roman #{$roman->id} sind ungültig. Der Export wurde abgebrochen.");
    $this->assertNoTemporaryArchivesExist();
})->with('KompendiumRomanArchiveDownloadTest.unsafeStoredLocations');
test('directory instead of source file aborts export', function (): void {
    $roman = $this->createRoman();
    Storage::disk('private')->makeDirectory($roman->dateipfad);
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', 'Die Datei "001 - Der Gott aus dem Eis.txt" kann nicht gelesen werden. Der Export wurde abgebrochen.');
    $this->assertNoTemporaryArchivesExist();
});
test('writer failure removes temporary archive and is logged', function (): void {
    $roman = $this->createRoman();
    Storage::disk('private')->put($roman->dateipfad, 'Inhalt');
    $this->mock(KompendiumRomanArchiveWriter::class, function ($mock): void {
        $mock->shouldReceive('write')->once()->andThrow(new RuntimeException('Interner technischer Fehler'));
    });
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', 'Das ZIP-Archiv konnte nicht erstellt werden.');
    $this->assertNoTemporaryArchivesExist();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Kompendium: ZIP-Export wurde abgebrochen.' && $context['user_id'] === $this->admin->id && $context['fehler'] === 'Das ZIP-Archiv konnte nicht erstellt werden.' && $context['exception'] instanceof KompendiumRomanArchiveException && $context['exception']->getPrevious() instanceof RuntimeException && $context['exception']->getPrevious()->getMessage() === 'Interner technischer Fehler');
});
test('empty archive created by writer is rejected and removed', function (): void {
    $roman = $this->createRoman();
    Storage::disk('private')->put($roman->dateipfad, 'Inhalt');
    $this->mock(KompendiumRomanArchiveWriter::class, function ($mock): void {
        $mock->shouldReceive('write')->once();
    });
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download-all'))->assertRedirect(route('kompendium.admin'))->assertSessionHas('error', 'Das erstellte ZIP-Archiv ist ungültig.');
    $this->assertNoTemporaryArchivesExist();
});
test('admin dashboard shows unfiltered archive download link', function (): void {
    $downloadUrl = route('kompendium.admin.romane.download-all');
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin', ['filterSerie' => 'missionmars', 'filterStatus' => 'indexiert', 'suchbegriff' => 'Mars']));
    $response->assertOk()->assertSee('data-testid="download-all-novels"', false)->assertSee('href="'.$downloadUrl.'"', false);
    expect($response->getContent())->toMatch('/<a(?=[^>]*data-testid="download-all-novels")(?=[^>]*href="'.preg_quote($downloadUrl, '/').'")(?![^>]*wire:navigate)[^>]*>/');
});
