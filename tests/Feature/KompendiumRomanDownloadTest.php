<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Admin\KompendiumRomanDownloadController;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumRomanDownloadTestFixtures::class);
covers(KompendiumRomanDownloadController::class);
dataset('KompendiumRomanDownloadTest.nonAdminRoles', static fn (): array => ['Mitglied' => [Role::Mitglied], 'Vorstand' => [Role::Vorstand], 'Kassenwart' => [Role::Kassenwart], 'Ehrenmitglied' => [Role::Ehrenmitglied]]);
dataset('KompendiumRomanDownloadTest.unsafeStoredLocations', static fn (): array => ['unbekannte Serie' => ['unbekannt', '001 - Geheim.txt', 'romane/unbekannt/001 - Geheim.txt'], 'Unterverzeichnis im Dateinamen' => ['maddrax', 'archiv/001 - Geheim.txt', 'romane/maddrax/archiv/001 - Geheim.txt'], 'Backslash im Dateinamen' => ['maddrax', 'archiv\001 - Geheim.txt', 'romane/maddrax/archiv\001 - Geheim.txt'], 'andere Dateiendung' => ['maddrax', '001 - Geheim.html', 'romane/maddrax/001 - Geheim.html'], 'abweichender Dateipfad' => ['maddrax', '001 - Erwartet.txt', 'romane/maddrax/001 - Andere Datei.txt']]);
dataset('KompendiumRomanDownloadTest.unsafePathsRejectedBeforeStorageAccess', static fn (): array => ['leerer Dateiname' => ['', 'romane/maddrax/'], 'Traversal' => ['001 - Geheim.txt', 'romane/maddrax/../001 - Geheim.txt'], 'Steuerzeichen' => ["001 - Geheim\n.txt", "romane/maddrax/001 - Geheim\n.txt"]]);
beforeEach(function (): void {
    Storage::fake('private');
    $this->admin = $this->createUserWithRole(Role::Admin);
});
test('admin can download current roman file', function (): void {
    $roman = $this->createRoman();
    Storage::disk('private')->put($roman->dateipfad, 'Aktueller Romantext');
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', $roman));
    $response->assertOk()->assertDownload($roman->dateiname)->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('Aktueller Romantext');
});
test('download uses current path and filename after rename', function (): void {
    $roman = $this->createRoman(['dateiname' => '009 - Neuer Titel.txt', 'dateipfad' => 'romane/maddrax/009 - Neuer Titel.txt', 'roman_nr' => 9, 'titel' => 'Neuer Titel']);
    Storage::disk('private')->put('romane/maddrax/009 - Alter Titel.txt', 'Alter Romantext');
    Storage::disk('private')->put($roman->dateipfad, 'Umbenannter Romantext');
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', $roman));
    $response->assertOk()->assertDownload('009 - Neuer Titel.txt');
    expect($response->streamedContent())->toBe('Umbenannter Romantext');
});
test('non admin cannot download roman file', function (Role $role): void {
    $roman = $this->createRoman();
    Storage::disk('private')->put($roman->dateipfad, 'Geheimer Romantext');
    $nonAdmin = $this->createUserWithRole($role);
    $this->actingAs($nonAdmin)->get(route('kompendium.admin.romane.download', $roman))->assertForbidden();
})->with('KompendiumRomanDownloadTest.nonAdminRoles');
test('guest is redirected to login', function (): void {
    $roman = $this->createRoman();
    $this->get(route('kompendium.admin.romane.download', $roman))->assertRedirect(route('login'));
});
test('unknown roman returns not found', function (): void {
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', ['roman' => 999999]))->assertNotFound();
});
test('missing file returns not found', function (): void {
    $roman = $this->createRoman();
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', $roman))->assertNotFound();
});
test('unsafe stored location cannot be downloaded', function (string $serie, string $dateiname, string $dateipfad): void {
    $roman = $this->createRoman(['serie' => $serie, 'dateiname' => $dateiname, 'dateipfad' => $dateipfad]);
    Storage::disk('private')->put($dateipfad, 'Darf nicht ausgeliefert werden');
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', $roman))->assertNotFound();
})->with('KompendiumRomanDownloadTest.unsafeStoredLocations');
test('unsafe path is rejected before storage access', function (string $dateiname, string $dateipfad): void {
    $roman = $this->createRoman(['dateiname' => $dateiname, 'dateipfad' => $dateipfad]);
    Storage::shouldReceive('disk')->never();
    $this->actingAs($this->admin)->get(route('kompendium.admin.romane.download', $roman))->assertNotFound();
})->with('KompendiumRomanDownloadTest.unsafePathsRejectedBeforeStorageAccess');
test('admin dashboard shows regular download link for running indexation', function (): void {
    $roman = $this->createRoman(['status' => 'indexierung_laeuft']);
    $downloadUrl = route('kompendium.admin.romane.download', $roman);
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin'));
    $response->assertOk()->assertSee('data-testid="download-btn-'.$roman->id.'"', false)->assertSee('href="'.$downloadUrl.'"', false);
    expect($response->getContent())->toMatch('/<a(?=[^>]*data-testid="download-btn-'.$roman->id.'")(?=[^>]*href="'.preg_quote($downloadUrl, '/').'")(?![^>]*wire:navigate)[^>]*>/');
});
