<?php

namespace Tests\Integration;

use App\Support\BundledDownloadLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BundledDownloadLocator::class)]
class BundledDownloadLocatorTest extends TestCase
{
    public function test_reports_the_size_of_bundled_rulebooks_without_database_seeding(): void
    {
        // Coverage must not depend on which test first initializes the database.
        foreach (['Regelwerk2001.pdf', 'Regelwerk2007.pdf'] as $filename) {
            $path = 'downloads/'.$filename;
            $source = resource_path($path);

            $this->assertFileExists($source);
            $this->assertSame(filesize($source), BundledDownloadLocator::fileSize($path));
        }
    }

    public function test_returns_no_size_for_a_missing_bundled_download(): void
    {
        $path = 'downloads/nonexistent-rulebook.pdf';

        $this->assertFileDoesNotExist(resource_path($path));
        $this->assertNull(BundledDownloadLocator::fileSize($path));
    }

    public function test_returns_no_size_for_a_path_outside_the_download_directory(): void
    {
        $this->assertNull(BundledDownloadLocator::fileSize('downloads/../views/pages/downloads.blade.php'));
    }
}
