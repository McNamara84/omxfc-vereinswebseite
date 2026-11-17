<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ReadCapture;
use Pest\Plugins\Tia\Recorder;
use Pest\TestSuite;

require dirname(__DIR__, 3).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/Support/TiaNativeStreamSync.php';

TestSuite::getInstance(dirname(__DIR__, 3), 'tests');
$path = tempnam(sys_get_temp_dir(), 'tia-native-sync-');
if ($path === false) {
    throw new RuntimeException('Cannot prepare the native synchronization fixture.');
}

$handle = null;
$results = [];
try {
    foreach (['native', 'recording'] as $mode) {
        if ($mode === 'recording') {
            ReadCapture::start(new Recorder);
        }
        try {
            $handle = fopen($path, 'w+b');
            if ($handle === false) {
                throw new RuntimeException('Cannot open the synchronization fixture.');
            }
            fwrite($handle, $mode);
            $wrapper = stream_get_meta_data($handle)['wrapper_data'] ?? null;
            $results[$mode] = [
                'recording' => $wrapper instanceof ReadCapture,
                'synced' => App\Services\Maddraxikon\fsync($handle),
            ];
            rewind($handle);
            $results[$mode]['contents'] = stream_get_contents($handle);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            ReadCapture::stop();
        }
    }
    $handle = fopen('php://memory', 'w+b');
    // An unsupported stream must still report the real native failure.
    $results['unsupported'] = @App\Services\Maddraxikon\fsync($handle);
    fclose($handle);
    try {
        App\Services\Maddraxikon\fsync($handle);
        $results['closed'] = false;
    } catch (TypeError) {
        $results['closed'] = true;
    }
    echo json_encode($results, JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
    if (is_resource($handle)) {
        fclose($handle);
    }
    ReadCapture::stop();
    unlink($path);
}
