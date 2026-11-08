<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Team;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(MembersTeamProvider::class);

test('returns team when exists', function (): void {
    $service = new MembersTeamProvider;
    $team = $service->getMembersTeamOrAbort();
    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->name)->toBe('Mitglieder');
});

test('redirects when team missing', function (): void {
    Team::query()->where('name', 'Mitglieder')->delete();
    Team::clearMembersTeamCache();
    session()->start();
    $service = new MembersTeamProvider;
    try {
        $service->getMembersTeamOrAbort();
        $this->fail('Expected HttpResponseException not thrown');
    } catch (HttpResponseException $e) {
        expect($e->getResponse()->headers->get('Location'))->toEqual(url('/'))
            ->and($e->getResponse()->getSession()->get('error'))->toEqual('Team "Mitglieder" nicht gefunden.');
    }
});
