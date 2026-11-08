<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ArbeitsgruppenKontaktNachricht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ArbeitsgruppenControllerTestFixtures::class);

test('ag leader cannot change name or leader', function (): void {
    $leader = $this->createMemberWithRole();
    $otherLeader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $this->actingAs($leader);
    $response = $this->put(route('arbeitsgruppen.update', $ag), ['name' => 'AG Neu', 'leader_id' => $otherLeader->id, 'description' => 'Desc', 'email' => 'ag@example.com', 'meeting_schedule' => 'montags']);
    $response->assertRedirect(route('arbeitsgruppen.index'));
    $this->assertDatabaseHas('teams', ['id' => $ag->id, 'name' => 'AG Test', 'user_id' => $leader->id, 'description' => 'Desc', 'email' => 'ag@example.com', 'meeting_schedule' => 'montags']);
});

test('ag leader cannot edit other team', function (): void {
    $leader = $this->createMemberWithRole();
    $otherLeader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $otherLeader->id, 'personal_team' => false, 'name' => 'Andere AG']);
    $this->actingAs($leader);
    $this->get(route('arbeitsgruppen.edit', $ag))->assertForbidden();
});

test('admin can update any team', function (): void {
    $admin = $this->createMemberWithRole('Admin');
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $this->actingAs($admin);
    $response = $this->put(route('arbeitsgruppen.update', $ag), ['name' => 'AG Admin', 'leader_id' => $admin->id, 'description' => null, 'email' => null, 'meeting_schedule' => null]);
    $response->assertRedirect(route('arbeitsgruppen.index'));
    $this->assertDatabaseHas('teams', ['id' => $ag->id, 'name' => 'AG Admin', 'user_id' => $admin->id]);
});

test('ag leader can access ag page', function (): void {
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'Meine AG']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $this->actingAs($leader);
    $this->get(route('ag.index'))->assertOk()->assertSee('Meine AG');
});

test('non leader cannot access ag page', function (): void {
    $member = $this->createMemberWithRole();
    $this->actingAs($member);
    $this->get(route('ag.index'))->assertForbidden();
});

test('admin not leader cannot access ag page', function (): void {
    $admin = $this->createMemberWithRole('Admin');
    $this->actingAs($admin);
    $this->get(route('ag.index'))->assertForbidden();
});

test('ag leader can add member with mitwirkender role', function (): void {
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $newUser = $this->createMemberWithRole();
    $this->actingAs($leader);
    $response = $this->post(route('arbeitsgruppen.add-member', $ag), ['user_id' => $newUser->id]);
    $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
    expect($ag->fresh()->hasUser($newUser))->toBeTrue()
        ->and($newUser->fresh()->hasTeamRole($ag->fresh(), Role::Mitwirkender->value))->toBeTrue();
});

test('admin can add member to any ag', function (): void {
    $admin = $this->createMemberWithRole('Admin');
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $newUser = $this->createMemberWithRole();
    $this->actingAs($admin);
    $response = $this->post(route('arbeitsgruppen.add-member', $ag), ['user_id' => $newUser->id]);
    $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
    expect($ag->fresh()->hasUser($newUser))->toBeTrue()
        ->and($newUser->fresh()->hasTeamRole($ag->fresh(), Role::Mitwirkender->value))->toBeTrue();
});

test('ag leader cannot add more than five members', function (): void {
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    for ($i = 0; $i < 4; $i++) {
        $user = $this->createMemberWithRole();
        $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
    }
    $extra = $this->createMemberWithRole();
    $this->actingAs($leader);
    $response = $this->from(route('arbeitsgruppen.edit', $ag))->post(route('arbeitsgruppen.add-member', $ag), ['user_id' => $extra->id]);
    $response->assertRedirect(route('arbeitsgruppen.edit', $ag));
    $response->assertSessionHasErrors('user_id', null, 'addTeamMember');
    expect($ag->fresh()->hasUser($extra))->toBeFalse();
});

test('edit page shows member roles', function (): void {
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $member = $this->createMemberWithRole();
    $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);
    $this->actingAs($leader);
    $this->get(route('arbeitsgruppen.edit', $ag))->assertOk()->assertSee('AG-Leiter')->assertSee('Mitwirkender');
});

test('ag overview does not show members', function (): void {
    $leader = $this->createMemberWithRole();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Test']);
    $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);
    $member = $this->createMemberWithRole();
    $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);
    $this->actingAs($leader);
    $this->get(route('ag.index'))->assertOk()->assertSee('AG Test')->assertDontSee($member->name);
});

test('public contact shows the public contact form', function (): void {
    $leader = User::factory()->create();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Kontakt', 'email' => 'ag-kontakt@example.com']);
    $this->get(route('arbeitsgruppen.kontakt', $ag))->assertOk()->assertSeeText('Kontakt zu AG Kontakt')->assertSee('action="'.route('arbeitsgruppen.kontakt.senden', $ag).'"', false)->assertDontSee('ag-kontakt@example.com', false)->assertDontSee('mailto:ag-kontakt@example.com', false);
});

test('public contact returns not found for non public teams', function (): void {
    $leader = User::factory()->create();
    $privateTeam = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => true, 'name' => 'Privates Team', 'email' => 'privat@example.com']);
    $this->get(route('arbeitsgruppen.kontakt', $privateTeam))->assertNotFound();
});

test('public contact returns not found when no mail address exists', function (): void {
    $leader = User::factory()->create();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Ohne Mail', 'email' => null]);
    $this->get(route('arbeitsgruppen.kontakt', $ag))->assertNotFound();
});

test('public contact form sends a server side mail without exposing the target address', function (): void {
    Mail::fake();
    $leader = User::factory()->create();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Kontakt', 'email' => 'ag-kontakt@example.com']);
    $this->post(route('arbeitsgruppen.kontakt.senden', $ag), ['name' => 'Martin', 'email' => 'martin@example.com', 'message' => 'Ich interessiere mich für eure Arbeitsgruppe und würde gerne mehr erfahren.', 'website' => ''])->assertRedirect(route('arbeitsgruppen.kontakt', $ag))->assertSessionHas('status');
    Mail::assertQueued(ArbeitsgruppenKontaktNachricht::class, fn (ArbeitsgruppenKontaktNachricht $mail) => $mail->hasTo($ag->email) && $mail->team->is($ag) && $mail->absenderName === 'Martin' && $mail->absenderEmail === 'martin@example.com' && $mail->nachricht === 'Ich interessiere mich für eure Arbeitsgruppe und würde gerne mehr erfahren.');
});

test('public contact form rejects honeypot submissions', function (): void {
    Mail::fake();
    $leader = User::factory()->create();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Kontakt', 'email' => 'ag-kontakt@example.com']);
    $response = $this->withHeader('referer', 'https://evil.example/redirect-me')->post(route('arbeitsgruppen.kontakt.senden', $ag), ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Diese Nachricht sollte nicht versendet werden.', 'website' => 'https://spam.invalid']);
    $response->assertRedirect(route('arbeitsgruppen.kontakt', $ag))->assertSessionHasErrors('error');
    $oldInput = $this->app['session.store']->getOldInput();
    expect($oldInput)->toBe(['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Diese Nachricht sollte nicht versendet werden.']);
    Mail::assertNothingQueued();
});

test('public contact form is rate limited after five requests', function (): void {
    Mail::fake();
    $leader = User::factory()->create();
    $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Kontakt', 'email' => 'ag-kontakt@example.com']);
    $server = ['REMOTE_ADDR' => '203.0.113.10'];
    for ($i = 1; $i <= 5; $i++) {
        $this->withServerVariables($server)->post(route('arbeitsgruppen.kontakt.senden', $ag), ['name' => "Interessent {$i}", 'email' => "interessent{$i}@example.com", 'message' => 'Ich interessiere mich für eure Arbeitsgruppe und würde gerne mitmachen.', 'website' => ''])->assertRedirect(route('arbeitsgruppen.kontakt', $ag))->assertSessionHasNoErrors();
    }
    $this->withServerVariables($server)->post(route('arbeitsgruppen.kontakt.senden', $ag), ['name' => 'Geblockter Interessent', 'email' => 'blocked@example.com', 'message' => 'Ich sollte wegen des Rate-Limits nicht mehr durchkommen.', 'website' => ''])->assertStatus(429);
    Mail::assertQueuedCount(5);
    Mail::assertNotQueued(ArbeitsgruppenKontaktNachricht::class, fn (ArbeitsgruppenKontaktNachricht $mail) => $mail->absenderEmail === 'blocked@example.com');
});
