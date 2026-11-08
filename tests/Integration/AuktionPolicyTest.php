<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use App\Policies\AuktionPolicy;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(AuktionPolicyTestFixtures::class);

covers(AuktionPolicy::class);

beforeEach(function (): void {
    $this->policy = new AuktionPolicy;
});

test('view any allows all non applicant member roles', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    expect($this->policy->viewAny($user))->toBeTrue();
})->with([[Role::Mitglied], [Role::Ehrenmitglied], [Role::Mitwirkender], [Role::Vorstand], [Role::Kassenwart], [Role::Admin]]);

test('view any denies anwaerter', function (): void {
    $user = $this->createUserWithRole(Role::Anwaerter);
    expect($this->policy->viewAny($user))->toBeFalse();
});

test('manage allows governance roles', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    expect($this->policy->manage($user))->toBeTrue();
})->with([[Role::Admin], [Role::Vorstand], [Role::Kassenwart]]);

test('manage denies regular members', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    expect($this->policy->manage($user))->toBeFalse();
});

test('bid allows configured bidding roles', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $auktion = Auktion::factory()->create();
    expect($this->policy->bid($user, $auktion))->toBeTrue();
})->with([[Role::Mitglied], [Role::Ehrenmitglied], [Role::Mitwirkender], [Role::Admin], [Role::Vorstand], [Role::Kassenwart]]);

test('bid denies non bidding roles', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $auktion = Auktion::factory()->create();
    expect($this->policy->bid($user, $auktion))->toBeFalse();
})->with([[Role::Anwaerter]]);

test('bid denies closed auctions even for regular member', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['status' => AuktionsStatus::Verkauft]);
    expect($this->policy->bid($user, $auktion))->toBeFalse();
});

test('call allows only vorstand', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $admin = $this->createUserWithRole(Role::Admin);
    $auktion = Auktion::factory()->create();
    expect($this->policy->call($vorstand, $auktion))->toBeTrue()
        ->and($this->policy->call($admin, $auktion))->toBeFalse();
});

test('delete denies auctions with existing bids', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name]);
    expect($this->policy->delete($admin, $auktion->fresh('gebote')))->toBeFalse();
});
