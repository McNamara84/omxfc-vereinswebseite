<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);
dataset('PhpDeprecationGuardTest.deprecationFixtures', static function (): iterable {
    yield 'native runtime' => ['imagedestroy(imagecreatetruecolor(1, 1));', 'imagedestroy'];
    yield 'compile time' => ['eval("\$value = (boolean) true;");', 'boolean'];
    yield 'Laravel handler' => ['$app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); trigger_error("Laravel guard fixture", E_USER_DEPRECATED);', 'Laravel guard fixture'];
    yield 'loaded dependency' => ['require getenv("PHP_DEPRECATIONS_LOG").".dependency.php";', 'imagedestroy'];
});
test('the test bootstrap rejects native and laravel deprecations', function (string $code, string $message): void {
    $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
    try {
        file_put_contents($log.'.dependency.php', '<?php imagedestroy(imagecreatetruecolor(1, 1));');
        $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; '.$code], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
        $process->mustRun();
        $this->fail('A deprecation must fail the PHP process.');
    } catch (ProcessFailedException $error) {
        expect($error->getProcess()->getExitCode())->toBe(1)
            ->and($error->getProcess()->getErrorOutput())->toContain('PHP deprecations detected')
            ->and($error->getProcess()->getErrorOutput())->toContain($message);
    } finally {
        foreach ([$log, $log.'.php.log', $log.'.dependency.php'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
})->with('PhpDeprecationGuardTest.deprecationFixtures');
test('a clean bootstrap succeeds', function (): void {
    $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
    try {
        $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; echo "clean";'], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
        $process->mustRun();
        expect($process->getOutput())->toBe('clean');
    } finally {
        foreach ([$log, $log.'.php.log'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});
test('missing native evidence is not a successful check', function (): void {
    $log = tempnam(sys_get_temp_dir(), 'omxfc-guard-fixture-');
    try {
        $process = new Process([PHP_BINARY, '-r', 'require "tests/bootstrap.php"; unlink(getenv("PHP_DEPRECATIONS_LOG").".php.log");'], dirname(__DIR__, 2), ['PHP_DEPRECATIONS_LOG' => $log]);
        $process->run();
        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('evidence could not be read');
    } finally {
        unlink($log);
    }
});
test('a successful http response cannot hide laravel deprecations', function (): void {
    $log = tempnam(sys_get_temp_dir(), 'omxfc-http-guard-');
    $router = $log.'.router.php';
    $root = dirname(__DIR__, 2);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    file_put_contents($router, '<?php require '.var_export($root.'/tests/bootstrap.php', true).'; $app = require '.var_export($root.'/bootstrap/app.php', true).'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); trigger_error("HTTP guard fixture", E_USER_DEPRECATED); echo "served";');
    $server = new Process([PHP_BINARY, '-S', $address, $router], $root, ['PHP_DEPRECATIONS_LOG' => $log]);
    try {
        $server->start();
        expect($server->waitUntil(static fn ($type, $output) => str_contains($output, 'Development Server')))->toBeTrue();
        $response = file_get_contents('http://'.$address, false, stream_context_create(['http' => ['timeout' => 10]]));
        expect($response)->toBe('served')
            ->and(file_get_contents($log))->toContain('HTTP guard fixture');
    } finally {
        $server->stop();
        foreach ([$log, $log.'.php.log', $router] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});
