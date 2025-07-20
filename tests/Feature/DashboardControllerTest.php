<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\MitgliedGenehmigtMail;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);
        return $user;
    }

    private function createApplicant(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Anw\xC3\xA4rter']);
        return $user;
    }

    public function test_admin_can_approve_applicant(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('team_user', [
            'user_id' => $applicant->id,
            'role' => 'Mitglied',
        ]);
        $this->assertNotNull($applicant->fresh()->mitglied_seit);
        Mail::assertSent(MitgliedGenehmigtMail::class);
    }

    public function test_admin_can_reject_applicant(): void
    {
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.reject', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('users', ['id' => $applicant->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $applicant->id]);
    }
}
