<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('teams can be created', function (): void {
    $user = $this->actingAdmin();
    Livewire::test(CreateTeamForm::class)->set(['state' => ['name' => 'Test Team']])->call('createTeam');
    expect($user->fresh()->ownedTeams()->where('name', 'Test Team')->exists())->toBeTrue();
});

test('non admin cannot create teams', function (): void {
    $user = $this->actingSimpleMember();
    Livewire::test(CreateTeamForm::class)->set(['state' => ['name' => 'Mein Team']])->call('createTeam')->assertForbidden();
});
