<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumRomanArchiveDownloadTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private User $admin;

    /** @var list<string> */
    private array $archivePaths = [];

    private function createRoman(array $attributes = []): KompendiumRoman
    {
        return KompendiumRoman::query()->create(array_merge(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen'], $attributes));
    }

    private function assertNoTemporaryArchivesExist(): void
    {
        $temporaryDirectory = Storage::disk('private')->path('temp/kompendium-exports');
        if (! is_dir($temporaryDirectory)) {
            $this->assertDirectoryDoesNotExist($temporaryDirectory);

            return;
        }
        expect(File::files($temporaryDirectory))->toBeEmpty();
    }
}
