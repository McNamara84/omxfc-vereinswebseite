<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BuiltInServerStaticPathResolver;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(BuiltInServerStaticPathResolverTestFixtures::class);

covers(BuiltInServerStaticPathResolver::class);

beforeEach(function (): void {
    $this->projectRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'omxfc-server-path-'.bin2hex(random_bytes(8));
    mkdir($this->projectRoot.DIRECTORY_SEPARATOR.'public', 0777, true);
    mkdir($this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'ag-logos', 0777, true);
    mkdir($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'nested', 0777, true);
    mkdir($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.git', 0777, true);
    mkdir($this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.private', 0777, true);
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'app.css', 'body {}');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', '<?php echo "index";');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.htaccess', 'deny from all');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config', '[core]');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'.secret', 'hidden');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'ag-logos'.DIRECTORY_SEPARATOR.'logo.svg', '<svg></svg>');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'.private'.DIRECTORY_SEPARATOR.'logo.svg', '<svg></svg>');
    file_put_contents($this->projectRoot.DIRECTORY_SEPARATOR.'secret.txt', 'secret');
});

afterEach(function (): void {
    $this->deleteDirectory($this->projectRoot);
});

test('resolve prefers public assets for normalized paths', function (): void {
    $resolvedPath = BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/app.css');
    expect($resolvedPath)->toBe($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'app.css');
});

test('resolve falls back to storage public assets when public storage path is unavailable', function (): void {
    $resolvedPath = BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/ag-logos/logo.svg');
    expect($resolvedPath)->toBe($this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'ag-logos'.DIRECTORY_SEPARATOR.'logo.svg');
});

test('resolve rejects directory traversal segments for public and storage paths', function (): void {
    expect(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/../secret.txt'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/../secret.txt'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/../../secret.txt'))->toBeNull();
});

test('resolve rejects backslash based traversal attempts', function (): void {
    expect(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/..\secret.txt'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/..\secret.txt'))->toBeNull();
});

test('resolve rejects php and dotfile targets', function (): void {
    expect(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/index.php'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/.htaccess'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/nested/.secret'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/.git/config'))->toBeNull()
        ->and(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/.private/logo.svg'))->toBeNull();
});

test('resolve rejects public symlink targets outside public root', function (): void {
    if (! function_exists('symlink')) {
        $this->markTestSkipped('Symlink creation is not supported in this environment.');
    }
    $outsideTarget = $this->projectRoot.DIRECTORY_SEPARATOR.'secret.txt';
    $linkPath = $this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'leak.txt';
    set_error_handler(static fn () => true);
    $symlinkCreated = symlink($outsideTarget, $linkPath);
    restore_error_handler();
    if (! $symlinkCreated) {
        $this->markTestSkipped('Symlink creation is not permitted in this environment.');
    }
    expect(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/leak.txt'))->toBeNull();
});
