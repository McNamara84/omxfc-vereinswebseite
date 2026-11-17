<?php

declare(strict_types=1);

namespace App\Services\Maddraxikon;

use Pest\Plugins\Tia\ReadCapture;

/**
 * Keep real disk synchronization under Pest 5.3.7's file-read instrumentation.
 * PHP cannot fsync a user-space stream, even when its stream_cast is native.
 * Loaded only by the TIA bootstrap; the ordinary suite uses PHP's fsync directly.
 *
 * @param  resource  $stream
 */
function fsync(mixed $stream): bool
{
    if (is_resource($stream)) {
        $wrapper = stream_get_meta_data($stream)['wrapper_data'] ?? null;
        if ($wrapper instanceof ReadCapture) {
            $stream = $wrapper->stream_cast(STREAM_CAST_AS_STREAM);
        }
    }

    return \fsync($stream);
}
