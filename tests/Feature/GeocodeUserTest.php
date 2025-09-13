<?php

namespace Tests\Feature;

use App\Jobs\GeocodeUser;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeocodeUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocode_job_can_be_disabled_via_environment(): void
    {
        Queue::fake();
        putenv('DISABLE_GEOCODING=true');

        User::factory()->create([
            'lat' => null,
            'lon' => null,
        ]);

        Queue::assertNotPushed(GeocodeUser::class);

        putenv('DISABLE_GEOCODING');
    }
}
