<?php

namespace App\Livewire\Teams;

use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;
use Mary\Traits\Toast;

class TeamMemberManager extends JetstreamTeamMemberManager
{
    use Toast;

    /**
     * Add a new team member to a team.
     */
    public function addTeamMember(): void
    {
        $this->resetErrorBag();

        if (Features::sendsTeamInvitations()) {
            app(InvitesTeamMembers::class)->invite(
                $this->user,
                $this->team,
                $this->addTeamMemberForm['email'],
                $this->addTeamMemberForm['role']
            );
        } else {
            app(AddsTeamMembers::class)->add(
                $this->user,
                $this->team,
                $this->addTeamMemberForm['email'],
                $this->addTeamMemberForm['role']
            );
        }

        $this->addTeamMemberForm = [
            'email' => '',
            'role' => null,
        ];

        $this->team = $this->team->fresh();

        $this->toast(
            type: 'success',
            title: __('Hinzugefügt.'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            timeout: 3000,
        );
    }
}
