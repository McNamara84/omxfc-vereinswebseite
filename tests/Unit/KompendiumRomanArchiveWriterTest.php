<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

pest()->extend(TestCase::class)->use(KompendiumRomanArchiveWriterTestFixtures::class);

covers(KompendiumRomanArchiveWriter::class);

afterEach(function (): void {
    foreach ($this->temporaryPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('writes all entries with requested archive paths', function (): void {
    $firstSource = $this->temporaryFile('Erster Inhalt');
    $secondSource = $this->temporaryFile('Zweiter Inhalt');
    $archivePath = $this->temporaryFile();
    (new KompendiumRomanArchiveWriter)->write($archivePath, [['sourcePath' => $firstSource, 'archivePath' => 'Maddrax - Die dunkle Zukunft der Erde/001 - Eins.txt'], ['sourcePath' => $secondSource, 'archivePath' => 'Mission Mars/001 - Zwei.txt']]);
    $zip = new ZipArchive;
    expect($zip->open($archivePath))->toBeTrue()
        ->and($zip->numFiles)->toBe(2)
        ->and($zip->getFromName('Maddrax - Die dunkle Zukunft der Erde/001 - Eins.txt'))->toBe('Erster Inhalt')
        ->and($zip->getFromName('Mission Mars/001 - Zwei.txt'))->toBe('Zweiter Inhalt')
        ->and($zip->close())->toBeTrue();
});

test('rejects directory as archive path', function (): void {
    expect(fn () => (new KompendiumRomanArchiveWriter)->write(sys_get_temp_dir(), []))->toThrow(KompendiumRomanArchiveException::class, 'Das ZIP-Archiv konnte nicht erstellt werden.');
});

test('rejects missing source file', function (): void {
    $archivePath = $this->temporaryFile();
    $missingSource = sys_get_temp_dir().'/kompendium-source-missing-'.bin2hex(random_bytes(8)).'.txt';
    expect(fn () => (new KompendiumRomanArchiveWriter)->write($archivePath, [['sourcePath' => $missingSource, 'archivePath' => 'Maddrax/001 - Fehlt.txt']]))->toThrow(KompendiumRomanArchiveException::class, 'Eine Roman-Datei konnte nicht zum ZIP-Archiv hinzugefügt werden.');
});
