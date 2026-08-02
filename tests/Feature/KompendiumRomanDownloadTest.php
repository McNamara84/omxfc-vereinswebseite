<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Admin\KompendiumRomanDownloadController;
use App\Models\KompendiumRoman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[CoversClass(KompendiumRomanDownloadController::class)]
class KompendiumRomanDownloadTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->admin = $this->createUserWithRole(Role::Admin);
    }

    public function test_admin_can_download_current_roman_file(): void
    {
        $roman = $this->createRoman();
        Storage::disk('private')->put($roman->dateipfad, 'Aktueller Romantext');

        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', $roman));

        $response->assertOk()
            ->assertDownload($roman->dateiname)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('Aktueller Romantext', $response->streamedContent());
    }

    public function test_download_uses_current_path_and_filename_after_rename(): void
    {
        $roman = $this->createRoman([
            'dateiname' => '009 - Neuer Titel.txt',
            'dateipfad' => 'romane/maddrax/009 - Neuer Titel.txt',
            'roman_nr' => 9,
            'titel' => 'Neuer Titel',
        ]);
        Storage::disk('private')->put('romane/maddrax/009 - Alter Titel.txt', 'Alter Romantext');
        Storage::disk('private')->put($roman->dateipfad, 'Umbenannter Romantext');

        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', $roman));

        $response->assertOk()
            ->assertDownload('009 - Neuer Titel.txt');
        $this->assertSame('Umbenannter Romantext', $response->streamedContent());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_download_roman_file(Role $role): void
    {
        $roman = $this->createRoman();
        Storage::disk('private')->put($roman->dateipfad, 'Geheimer Romantext');
        $nonAdmin = $this->createUserWithRole($role);

        $this->actingAs($nonAdmin)
            ->get(route('kompendium.admin.romane.download', $roman))
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
        $roman = $this->createRoman();

        $this->get(route('kompendium.admin.romane.download', $roman))
            ->assertRedirect(route('login'));
    }

    public function test_unknown_roman_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', ['roman' => 999999]))
            ->assertNotFound();
    }

    public function test_missing_file_returns_not_found(): void
    {
        $roman = $this->createRoman();

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', $roman))
            ->assertNotFound();
    }

    #[DataProvider('unsafeStoredLocations')]
    public function test_unsafe_stored_location_cannot_be_downloaded(
        string $serie,
        string $dateiname,
        string $dateipfad,
    ): void {
        $roman = $this->createRoman([
            'serie' => $serie,
            'dateiname' => $dateiname,
            'dateipfad' => $dateipfad,
        ]);
        Storage::disk('private')->put($dateipfad, 'Darf nicht ausgeliefert werden');

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', $roman))
            ->assertNotFound();
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
        ];
    }

    #[DataProvider('unsafePathsRejectedBeforeStorageAccess')]
    public function test_unsafe_path_is_rejected_before_storage_access(
        string $dateiname,
        string $dateipfad,
    ): void {
        $roman = $this->createRoman([
            'dateiname' => $dateiname,
            'dateipfad' => $dateipfad,
        ]);
        Storage::shouldReceive('disk')->never();

        $this->actingAs($this->admin)
            ->get(route('kompendium.admin.romane.download', $roman))
            ->assertNotFound();
    }

    public static function unsafePathsRejectedBeforeStorageAccess(): array
    {
        return [
            'leerer Dateiname' => [
                '',
                'romane/maddrax/',
            ],
            'Traversal' => [
                '001 - Geheim.txt',
                'romane/maddrax/../001 - Geheim.txt',
            ],
            'Steuerzeichen' => [
                "001 - Geheim\n.txt",
                "romane/maddrax/001 - Geheim\n.txt",
            ],
        ];
    }

    public function test_admin_dashboard_shows_regular_download_link_for_running_indexation(): void
    {
        $roman = $this->createRoman(['status' => 'indexierung_laeuft']);
        $downloadUrl = route('kompendium.admin.romane.download', $roman);

        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin'));

        $response->assertOk()
            ->assertSee('data-testid="download-btn-'.$roman->id.'"', false)
            ->assertSee('href="'.$downloadUrl.'"', false);

        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*data-testid="download-btn-'.$roman->id.'")(?=[^>]*href="'.preg_quote($downloadUrl, '/').'")(?![^>]*wire:navigate)[^>]*>/',
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
}
