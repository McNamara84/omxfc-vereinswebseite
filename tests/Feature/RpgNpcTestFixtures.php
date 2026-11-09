<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;

trait RpgNpcTestFixtures
{
    use RefreshDatabase, RpgCombatFixtures;

    protected function npcInput(array $extra = []): array
    {
        return array_replace(['submission_key' => (string) Str::uuid(), 'template_key' => 'androne'], $extra);
    }
}
