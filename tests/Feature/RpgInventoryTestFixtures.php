<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgInventoryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgInventoryFixtures;

trait RpgInventoryTestFixtures
{
    use RefreshDatabase, RpgInventoryFixtures;

    private function state(): array
    {
        return app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    }
}
