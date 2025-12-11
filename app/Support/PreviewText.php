<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class PreviewText
{
    public static function make(mixed $content, int $limit): Stringable
    {
        return Str::of((string) strip_tags($content ?? ''))
            ->squish()
            ->limit($limit);
    }
}
