<?php

namespace Tests\Unit;

use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(KompendiumRomanArchiveWriter::class)]
class KompendiumRomanArchiveWriterTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_writes_all_entries_with_requested_archive_paths(): void
    {
        $firstSource = $this->temporaryFile('Erster Inhalt');
        $secondSource = $this->temporaryFile('Zweiter Inhalt');
        $archivePath = $this->temporaryFile();

        (new KompendiumRomanArchiveWriter)->write($archivePath, [
            [
                'sourcePath' => $firstSource,
                'archivePath' => 'Maddrax - Die dunkle Zukunft der Erde/001 - Eins.txt',
            ],
            [
                'sourcePath' => $secondSource,
                'archivePath' => 'Mission Mars/001 - Zwei.txt',
            ],
        ]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivePath));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame(
            'Erster Inhalt',
            $zip->getFromName('Maddrax - Die dunkle Zukunft der Erde/001 - Eins.txt'),
        );
        $this->assertSame('Zweiter Inhalt', $zip->getFromName('Mission Mars/001 - Zwei.txt'));
        $this->assertTrue($zip->close());
    }

    public function test_rejects_directory_as_archive_path(): void
    {
        $this->expectException(KompendiumRomanArchiveException::class);
        $this->expectExceptionMessage('Das ZIP-Archiv konnte nicht erstellt werden.');

        (new KompendiumRomanArchiveWriter)->write(sys_get_temp_dir(), []);
    }

    public function test_rejects_missing_source_file(): void
    {
        $archivePath = $this->temporaryFile();
        $missingSource = sys_get_temp_dir().'/kompendium-source-missing-'.bin2hex(random_bytes(8)).'.txt';

        $this->expectException(KompendiumRomanArchiveException::class);
        $this->expectExceptionMessage('Eine Roman-Datei konnte nicht zum ZIP-Archiv hinzugefügt werden.');

        (new KompendiumRomanArchiveWriter)->write($archivePath, [[
            'sourcePath' => $missingSource,
            'archivePath' => 'Maddrax/001 - Fehlt.txt',
        ]]);
    }

    private function temporaryFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kompendium-test-');
        $this->assertNotFalse($path);
        $this->temporaryPaths[] = $path;

        if ($contents !== '') {
            $this->assertNotFalse(file_put_contents($path, $contents));
        }

        return $path;
    }
}
