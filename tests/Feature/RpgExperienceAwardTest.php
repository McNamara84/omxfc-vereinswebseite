<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\RpgExperienceEntry;
use App\Services\RpgExperienceAwardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgProgressionFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
});

test('award creates one credit and activity even when retried', function (): void {
    $input = $this->awardInput();
    $service = app(RpgExperienceAwardService::class);
    $first = $service->award($this->leader, $input);
    expect($service->award($this->leader, $input)->id)->toBe($first->id)
        ->and($this->character->experienceBalance())->toBe(60)
        ->and(RpgExperienceEntry::count())->toBe(1)
        ->and(Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count())->toBe(1)
        ->and($this->character->initial_payload)->toBe($this->character->payload);
});

test('zero award is documented without public activity', function (): void {
    $this->credit(0);
    expect(RpgExperienceEntry::count())->toBe(1)
        ->and(Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count())->toBe(0);
});

test('changed replay is rejected', function (): void {
    $input = $this->awardInput();
    app(RpgExperienceAwardService::class)->award($this->leader, $input);
    $input['title'] = 'Andere Angaben';
    expect(fn () => app(RpgExperienceAwardService::class)->award($this->leader, $input))->toThrow(ValidationException::class);
});

test('admin alone may not award points', function (): void {
    $admin = $this->progressionMember(Role::Admin);
    expect(fn () => app(RpgExperienceAwardService::class)->award($admin, $this->awardInput()))->toThrow(AuthorizationException::class);
});

test('former member cannot receive points', function (): void {
    $this->rpgTeam->users()->detach($this->player);
    try {
        $this->credit();
        $this->fail('Award should fail');
    } catch (ValidationException) {
        expect(RpgExperienceEntry::count())->toBe(0);
        $this->assertDatabaseCount('rpg_adventures', 0);
    }
});

test('credit and activity roll back together', function (): void {
    Activity::creating(function (Activity $activity): void {
        if ($activity->action === Activity::ACTION_RPG_EXPERIENCE_AWARDED) {
            throw new \RuntimeException('Simulated activity failure');
        }
    });
    try {
        $this->credit();
        $this->fail('Expected simulated failure');
    } catch (\RuntimeException) {
        expect(RpgExperienceEntry::count())->toBe(0);
        $this->assertDatabaseCount('rpg_adventures', 0);
    } finally {
        Activity::flushEventListeners();
    }
});
