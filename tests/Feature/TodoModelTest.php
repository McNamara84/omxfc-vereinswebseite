<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TodoModelTestFixtures::class);

test('can be assigned to checks membership role', function (): void {
    $member = $this->createMember();
    $todo = $this->createTodo($member);
    expect($todo->canBeAssignedTo($member))->toBeTrue();
    $outsider = User::factory()->create();
    expect($todo->canBeAssignedTo($outsider))->toBeFalse();
});

test('can be created by requires elevated role', function (): void {
    $admin = $this->createMember('Admin');
    $todo = $this->createTodo($admin);
    expect($todo->canBeCreatedBy($admin))->toBeTrue();
    $member = $this->createMember();
    expect($todo->canBeCreatedBy($member))->toBeFalse();
});

test('can be verified by only admin or similar', function (): void {
    $admin = $this->createMember('Admin');
    $todo = $this->createTodo($admin);
    expect($todo->canBeVerifiedBy($admin))->toBeTrue();
    $member = $this->createMember();
    expect($todo->canBeVerifiedBy($member))->toBeFalse();
});
