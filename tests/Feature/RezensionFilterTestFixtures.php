<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;

trait RezensionFilterTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function putBookData(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'], ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1']]));
    }
}
