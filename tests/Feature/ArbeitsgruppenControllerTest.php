<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ArbeitsgruppenKontaktNachricht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ArbeitsgruppenControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMemberWithRole(string $role = 'Mitglied'): User
    {
        $memberTeam = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $memberTeam->id]);
        $memberTeam->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_ag_leader_cannot_change_name_or_leader(): void
    {
        $leader = $this->createMemberWithRole();
        $otherLeader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $this->actingAs($leader);

        $response = $this->put(route('arbeitsgruppen.update', $ag), [
            'name' => 'AG Neu',
            'leader_id' => $otherLeader->id,
            'description' => 'Desc',
            'email' => 'ag@example.com',
            'meeting_schedule' => 'montags',
        ]);

        $response->assertRedirect(route('arbeitsgruppen.index'));
        $this->assertDatabaseHas('teams', [
            'id' => $ag->id,
            'name' => 'AG Test',
            'user_id' => $leader->id,
            'description' => 'Desc',
            'email' => 'ag@example.com',
            'meeting_schedule' => 'montags',
        ]);
    }

    public function test_ag_leader_cannot_edit_other_team(): void
    {
        $leader = $this->createMemberWithRole();
        $otherLeader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $otherLeader->id,
            'personal_team' => false,
            'name' => 'Andere AG',
        ]);

        $this->actingAs($leader);

        $this->get(route('arbeitsgruppen.edit', $ag))->assertForbidden();
    }

    public function test_admin_can_update_any_team(): void
    {
        $admin = $this->createMemberWithRole('Admin');
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);

        $this->actingAs($admin);

        $response = $this->put(route('arbeitsgruppen.update', $ag), [
            'name' => 'AG Admin',
            'leader_id' => $admin->id,
            'description' => null,
            'email' => null,
            'meeting_schedule' => null,
        ]);

        $response->assertRedirect(route('arbeitsgruppen.index'));
        $this->assertDatabaseHas('teams', [
            'id' => $ag->id,
            'name' => 'AG Admin',
            'user_id' => $admin->id,
        ]);
    }

    public function test_ag_leader_can_access_ag_page(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'Meine AG',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $this->actingAs($leader);

        $this->get(route('ag.index'))
            ->assertOk()
            ->assertSee('Meine AG');
    }

    public function test_non_leader_cannot_access_ag_page(): void
    {
        $member = $this->createMemberWithRole();

        $this->actingAs($member);

        $this->get(route('ag.index'))->assertForbidden();
    }

    public function test_admin_not_leader_cannot_access_ag_page(): void
    {
        $admin = $this->createMemberWithRole('Admin');

        $this->actingAs($admin);

        $this->get(route('ag.index'))->assertForbidden();
    }

    public function test_ag_leader_can_add_member_with_mitwirkender_role(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $newUser = $this->createMemberWithRole();

        $this->actingAs($leader);

        $response = $this->post(route('arbeitsgruppen.add-member', $ag), [
            'user_id' => $newUser->id,
        ]);

        $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
        $this->assertTrue($ag->fresh()->hasUser($newUser));
        $this->assertTrue($newUser->fresh()->hasTeamRole($ag->fresh(), Role::Mitwirkender->value));
    }

    public function test_admin_can_add_member_to_any_ag(): void
    {
        $admin = $this->createMemberWithRole('Admin');
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $newUser = $this->createMemberWithRole();

        $this->actingAs($admin);

        $response = $this->post(route('arbeitsgruppen.add-member', $ag), [
            'user_id' => $newUser->id,
        ]);

        $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
        $this->assertTrue($ag->fresh()->hasUser($newUser));
        $this->assertTrue($newUser->fresh()->hasTeamRole($ag->fresh(), Role::Mitwirkender->value));
    }

    public function test_ag_leader_cannot_add_more_than_five_members(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        for ($i = 0; $i < 4; $i++) {
            $user = $this->createMemberWithRole();
            $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
        }

        $extra = $this->createMemberWithRole();
        $this->actingAs($leader);

        $response = $this->from(route('arbeitsgruppen.edit', $ag))
            ->post(route('arbeitsgruppen.add-member', $ag), [
                'user_id' => $extra->id,
            ]);

        $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
        $response->assertSessionHasErrors('user_id', null, 'addTeamMember');
        $this->assertFalse($ag->fresh()->hasUser($extra));
    }

    public function test_edit_page_shows_member_roles(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $member = $this->createMemberWithRole();
        $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);

        $this->actingAs($leader);

        $this->get(route('arbeitsgruppen.edit', $ag))
            ->assertOk()
            ->assertSee('AG-Leiter')
            ->assertSee('Mitwirkender');
    }

    public function test_ag_overview_does_not_show_members(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        $member = $this->createMemberWithRole();
        $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);

        $this->actingAs($leader);

        $this->get(route('ag.index'))
            ->assertOk()
            ->assertSee('AG Test')
            ->assertDontSee($member->name);
    }

    public function test_public_contact_shows_the_public_contact_form(): void
    {
        $leader = User::factory()->create();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Kontakt',
            'email' => 'ag-kontakt@example.com',
        ]);

        $this->get(route('arbeitsgruppen.kontakt', $ag))
            ->assertOk()
            ->assertSeeText('Kontakt zu AG Kontakt')
            ->assertSee('action="'.route('arbeitsgruppen.kontakt.senden', $ag).'"', false)
            ->assertDontSee('ag-kontakt@example.com', false)
            ->assertDontSee('mailto:ag-kontakt@example.com', false);
    }

    public function test_public_contact_returns_not_found_for_non_public_teams(): void
    {
        $leader = User::factory()->create();
        $privateTeam = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => true,
            'name' => 'Privates Team',
            'email' => 'privat@example.com',
        ]);

        $this->get(route('arbeitsgruppen.kontakt', $privateTeam))
            ->assertNotFound();
    }

    public function test_public_contact_returns_not_found_when_no_mail_address_exists(): void
    {
        $leader = User::factory()->create();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Ohne Mail',
            'email' => null,
        ]);

        $this->get(route('arbeitsgruppen.kontakt', $ag))
            ->assertNotFound();
    }

    public function test_public_contact_form_sends_a_server_side_mail_without_exposing_the_target_address(): void
    {
        Mail::fake();

        $leader = User::factory()->create();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Kontakt',
            'email' => 'ag-kontakt@example.com',
        ]);

        $this->post(route('arbeitsgruppen.kontakt.senden', $ag), [
            'name' => 'Martin',
            'email' => 'martin@example.com',
            'message' => 'Ich interessiere mich für eure Arbeitsgruppe und würde gerne mehr erfahren.',
            'website' => '',
        ])
            ->assertRedirect(route('arbeitsgruppen.kontakt', $ag))
            ->assertSessionHas('status');

        Mail::assertSent(ArbeitsgruppenKontaktNachricht::class, function (ArbeitsgruppenKontaktNachricht $mail) use ($ag) {
            return $mail->team->is($ag)
                && $mail->absenderName === 'Martin'
                && $mail->absenderEmail === 'martin@example.com'
                && $mail->nachricht === 'Ich interessiere mich für eure Arbeitsgruppe und würde gerne mehr erfahren.';
        });
    }

    public function test_public_contact_form_rejects_honeypot_submissions(): void
    {
        Mail::fake();

        $leader = User::factory()->create();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Kontakt',
            'email' => 'ag-kontakt@example.com',
        ]);

        $this->from(route('arbeitsgruppen.kontakt', $ag))
            ->post(route('arbeitsgruppen.kontakt.senden', $ag), [
                'name' => 'Bot',
                'email' => 'bot@example.com',
                'message' => 'Diese Nachricht sollte nicht versendet werden.',
                'website' => 'https://spam.invalid',
            ])
            ->assertRedirect(route('arbeitsgruppen.kontakt', $ag))
            ->assertSessionHasErrors('error');

        Mail::assertNothingSent();
    }
}
