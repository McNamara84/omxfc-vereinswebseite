<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_admin_can_view_newsletter_form(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertOk();
    }

    public function test_vorstand_cannot_view_newsletter_form(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertForbidden();
    }
}
