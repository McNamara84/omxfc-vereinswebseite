<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PhpDeprecationGuardTest extends TestCase
{
    #[DataProvider('deprecationFixtures')]
    public function test_the_test_bootstrap_rejects_native_and_laravel_deprecations(string $code, string $message): void
    {
        $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
        try {
            file_put_contents($log.'.dependency.php', '<?php imagedestroy(imagecreatetruecolor(1, 1));');
            $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; '.$code], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
            $process->mustRun();
            $this->fail('A deprecation must fail the PHP process.');
        } catch (ProcessFailedException $error) {
            $this->assertSame(1, $error->getProcess()->getExitCode());
            $this->assertStringContainsString('PHP deprecations detected', $error->getProcess()->getErrorOutput());
            $this->assertStringContainsString($message, $error->getProcess()->getErrorOutput());
        } finally {
            foreach ([$log, $log.'.php.log', $log.'.dependency.php'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public static function deprecationFixtures(): iterable
    {
        yield 'native runtime' => ['imagedestroy(imagecreatetruecolor(1, 1));', 'imagedestroy'];
        yield 'compile time' => ['eval("\$value = (boolean) true;");', 'boolean'];
        yield 'Laravel handler' => [
            '$app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); trigger_error("Laravel guard fixture", E_USER_DEPRECATED);',
            'Laravel guard fixture',
        ];
        yield 'loaded dependency' => [
            'require getenv("PHP_DEPRECATIONS_LOG").".dependency.php";',
            'imagedestroy',
        ];
    }

    public function test_a_clean_bootstrap_succeeds(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
        try {
            $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; echo "clean";'], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
            $process->mustRun();
            $this->assertSame('clean', $process->getOutput());
        } finally {
            foreach ([$log, $log.'.php.log'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_missing_native_evidence_is_not_a_successful_check(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
        try {
            $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; unlink(getenv("PHP_DEPRECATIONS_LOG").".php.log");'], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
            $process->run();
            $this->assertSame(1, $process->getExitCode());
            $this->assertStringContainsString('evidence could not be read', $process->getErrorOutput());
        } finally {
            unlink($log);
        }
    }

    public function test_a_successful_http_response_cannot_hide_laravel_deprecations(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'omxfc-http-guard-');
        $router = $log.'.router.php';
        $root = dirname(__DIR__, 2);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        file_put_contents($router, '<?php require '.var_export($root.'/tests/bootstrap.php', true).'; $app = require '.var_export($root.'/bootstrap/app.php', true).'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); trigger_error("HTTP guard fixture", E_USER_DEPRECATED); echo "served";');
        $server = new Process([PHP_BINARY, '-S', $address, $router], $root, ['PHP_DEPRECATIONS_LOG' => $log]);
        try {
            $server->start();
            $this->assertTrue($server->waitUntil(static fn ($type, $output) => str_contains($output, 'Development Server')));
            $response = file_get_contents('http://'.$address, false, stream_context_create(['http' => ['timeout' => 10]]));
            $this->assertSame('served', $response);
            $this->assertStringContainsString('HTTP guard fixture', file_get_contents($log));
        } finally {
            $server->stop();
            foreach ([$log, $log.'.php.log', $router] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
