<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Laravel\Jetstream\Mail\TeamInvitation;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('team members can be invited to team', function (): void {
    if (! Features::sendsTeamInvitations()) {
        $this->markTestSkipped('Team invitations not enabled.');
    }
    Mail::fake();
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('addTeamMemberForm', ['email' => 'test@example.com', 'role' => Role::Admin->value])->call('addTeamMember');
    Mail::assertQueued(TeamInvitation::class);
    expect($user->currentTeam->fresh()->teamInvitations)->toHaveCount(1);
});

test('team member invitations can be cancelled', function (): void {
    if (! Features::sendsTeamInvitations()) {
        $this->markTestSkipped('Team invitations not enabled.');
    }
    Mail::fake();
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    // Add the team member...
    $component = Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('addTeamMemberForm', ['email' => 'test@example.com', 'role' => Role::Admin->value])->call('addTeamMember');
    $invitationId = $user->currentTeam->fresh()->teamInvitations->first()->id;
    // Cancel the team invitation...
    $component->call('cancelTeamInvitation', $invitationId);
    expect($user->currentTeam->fresh()->teamInvitations)->toHaveCount(0);
});
