<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\GeocodeUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use ReflectionClass;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(GeocodeUser::class);

test('job geokodiert benutzer und speichert koordinaten', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team?->id, 'plz' => '12345', 'land' => 'Deutschland', 'lat' => null, 'lon' => null]);
    $job = new GeocodeUser($user);
    $job->handle();
    $user->refresh();
    expect((float) $user->lat)->toBe((float) self::DEFAULT_LAT)
        ->and((float) $user->lon)->toBe((float) self::DEFAULT_LON);
});

test('job verwendet laravel 13 queue attribute fuer retry timeout und backoff', function (): void {
    $reflection = new ReflectionClass(GeocodeUser::class);
    $triesAttributes = $reflection->getAttributes(Tries::class);
    $timeoutAttributes = $reflection->getAttributes(Timeout::class);
    $backoffAttributes = $reflection->getAttributes(Backoff::class);
    expect($triesAttributes)->toHaveCount(1)
        ->and($timeoutAttributes)->toHaveCount(1)
        ->and($backoffAttributes)->toHaveCount(1);
    $tries = $triesAttributes[0]->newInstance();
    $timeout = $timeoutAttributes[0]->newInstance();
    $backoff = $backoffAttributes[0]->newInstance();
    expect($tries->tries)->toBe(3)
        ->and($timeout->timeout)->toBe(30)
        ->and($backoff->backoff)->toBe(300);
});
