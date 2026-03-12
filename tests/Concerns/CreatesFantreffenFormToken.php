<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Crypt;

trait CreatesFantreffenFormToken
{
    private function validFormToken(): string
    {
        $minFormTime = (int) config('services.fantreffen.min_form_time', 3);

        return Crypt::encryptString((string) (time() - $minFormTime - 5));
    }
}
