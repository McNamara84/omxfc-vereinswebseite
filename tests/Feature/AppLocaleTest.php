<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_uses_german_locale(): void
    {
        $this->assertSame('de', app()->getLocale());
    }
}
