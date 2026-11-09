<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\BundledDownloadLocator;
use Tests\TestCase;

pest()->extend(TestCase::class);

covers(BundledDownloadLocator::class);

test('reports the size of bundled rulebooks without database seeding', function (): void {
    // Coverage must not depend on which test first initializes the database.
    foreach (['Regelwerk2001.pdf', 'Regelwerk2007.pdf'] as $filename) {
        $path = 'downloads/'.$filename;
        $source = resource_path($path);
        expect($source)->toBeFile()
            ->and(BundledDownloadLocator::fileSize($path))->toBe(filesize($source));
    }
});

test('returns no size for a missing bundled download', function (): void {
    $path = 'downloads/nonexistent-rulebook.pdf';
    $this->assertFileDoesNotExist(resource_path($path));
    expect(BundledDownloadLocator::fileSize($path))->toBeNull();
});

test('returns no size for a path outside the download directory', function (): void {
    expect(BundledDownloadLocator::fileSize('downloads/../views/pages/downloads.blade.php'))->toBeNull();
});
