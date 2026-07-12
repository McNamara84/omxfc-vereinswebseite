<?php

namespace Tests\Unit;

use App\Support\BuiltInServerStaticPathResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuiltInServerStaticPathResolver::class)]
class BuiltInServerStaticPathResolverTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);

        parent::tearDown();
    }

    #[Test]
    public function resolve_prefers_public_assets_for_normalized_paths(): void
    {
        $resolvedPath = BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/app.css');

        $this->assertSame($this->projectRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'app.css', $resolvedPath);
    }

    #[Test]
    public function resolve_falls_back_to_storage_public_assets_when_public_storage_path_is_unavailable(): void
    {
        $resolvedPath = BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/ag-logos/logo.svg');

        $this->assertSame(
            $this->projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'ag-logos'.DIRECTORY_SEPARATOR.'logo.svg',
            $resolvedPath
        );
    }

    #[Test]
    public function resolve_rejects_directory_traversal_segments_for_public_and_storage_paths(): void
    {
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/../secret.txt'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/../secret.txt'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/../../secret.txt'));
    }

    #[Test]
    public function resolve_rejects_backslash_based_traversal_attempts(): void
    {
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/..\\secret.txt'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/..\\secret.txt'));
    }

    #[Test]
    public function resolve_rejects_php_and_dotfile_targets(): void
    {
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/index.php'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/.htaccess'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/nested/.secret'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/.git/config'));
        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/storage/.private/logo.svg'));
    }

    #[Test]
    public function resolve_rejects_public_symlink_targets_outside_public_root(): void
    {
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

        $this->assertNull(BuiltInServerStaticPathResolver::resolve($this->projectRoot, '/leak.txt'));
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);

                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
