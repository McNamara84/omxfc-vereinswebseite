<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GeocodeUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(GeocodeUser::class)]
class GeocodeUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_geokodiert_benutzer_und_speichert_koordinaten(): void
    {
        $team = Team::membersTeam();

        $user = User::factory()->create([
            'current_team_id' => $team?->id,
            'plz' => '12345',
            'land' => 'Deutschland',
            'lat' => null,
            'lon' => null,
        ]);

        $job = new GeocodeUser($user);
        $job->handle();

        $user->refresh();

        $this->assertSame((float) self::DEFAULT_LAT, (float) $user->lat);
        $this->assertSame((float) self::DEFAULT_LON, (float) $user->lon);
    }

    #[Test]
    public function job_verwendet_laravel_13_queue_attribute_fuer_retry_timeout_und_backoff(): void
    {
        $reflection = new ReflectionClass(GeocodeUser::class);

        $triesAttributes = $reflection->getAttributes(Tries::class);
        $timeoutAttributes = $reflection->getAttributes(Timeout::class);
        $backoffAttributes = $reflection->getAttributes(Backoff::class);

        $this->assertCount(1, $triesAttributes);
        $this->assertCount(1, $timeoutAttributes);
        $this->assertCount(1, $backoffAttributes);

        $tries = $triesAttributes[0]->newInstance();
        $timeout = $timeoutAttributes[0]->newInstance();
        $backoff = $backoffAttributes[0]->newInstance();

        $this->assertSame(3, $tries->tries);
        $this->assertSame(30, $timeout->timeout);
        $this->assertSame(300, $backoff->backoff);
    }
}
