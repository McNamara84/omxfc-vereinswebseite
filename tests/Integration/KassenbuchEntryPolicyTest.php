<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\Role;
use App\Models\KassenbuchEditRequest;
use App\Models\Team;
use App\Policies\KassenbuchEntryPolicy;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KassenbuchEntryPolicyTestFixtures::class);

covers(KassenbuchEntryPolicy::class);

beforeEach(function (): void {
    $this->policy = new KassenbuchEntryPolicy;
    $this->team = Team::membersTeam();
});

test('view all allows finance roles', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    expect($this->policy->viewAll($user))->toBeTrue();
})->with([[Role::Vorstand], [Role::Admin], [Role::Kassenwart]]);

test('view all denies regular member', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    expect($this->policy->viewAll($user))->toBeFalse();
});

test('manage allows kassenwart and admin', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    expect($this->policy->manage($user))->toBeTrue();
})->with([[Role::Kassenwart], [Role::Admin]]);

test('manage denies vorstand role', function (): void {
    $user = $this->createUserWithRole(Role::Vorstand);
    expect($this->policy->manage($user))->toBeFalse();
});

test('manage denies regular members', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    expect($this->policy->manage($user))->toBeFalse();
});

test('request edit allows kassenwart and admin', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $entry = $this->createKassenbuchEntry($user);
    expect($this->policy->requestEdit($user, $entry))->toBeTrue();
})->with([[Role::Kassenwart], [Role::Admin]]);

test('request edit denies vorstand', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($this->policy->requestEdit($vorstand, $entry))->toBeFalse();
});

test('request edit denies mitglied', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $mitglied = $this->createUserWithRole(Role::Mitglied);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($this->policy->requestEdit($mitglied, $entry))->toBeFalse();
});

test('request delete allows kassenwart and admin', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $entry = $this->createKassenbuchEntry($user);
    expect($this->policy->requestDelete($user, $entry))->toBeTrue();
})->with([[Role::Kassenwart], [Role::Admin]]);

test('request delete denies vorstand', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($this->policy->requestDelete($vorstand, $entry))->toBeFalse();
});

test('request delete denies mitglied', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $mitglied = $this->createUserWithRole(Role::Mitglied);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($this->policy->requestDelete($mitglied, $entry))->toBeFalse();
});

test('edit allows with approved request', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($user);
    // Create approved request
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $user->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    expect($this->policy->edit($user, $entry))->toBeTrue();
})->with([[Role::Kassenwart], [Role::Admin]]);

test('edit denies without approved request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($this->policy->edit($kassenwart, $entry))->toBeFalse();
});

test('edit denies with pending request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    expect($this->policy->edit($kassenwart, $entry))->toBeFalse();
});

test('edit denies vorstand even with approved request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    expect($this->policy->edit($vorstand, $entry))->toBeFalse();
});

test('process edit request allows vorstand and admin', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    expect($this->policy->processEditRequest($user))->toBeTrue();
})->with([[Role::Vorstand], [Role::Admin]]);

test('process edit request denies kassenwart', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    expect($this->policy->processEditRequest($kassenwart))->toBeFalse();
});

test('process edit request denies mitglied', function (): void {
    $mitglied = $this->createUserWithRole(Role::Mitglied);
    expect($this->policy->processEditRequest($mitglied))->toBeFalse();
});
