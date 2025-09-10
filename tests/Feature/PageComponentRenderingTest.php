<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageComponentRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_component_renders_with_default_classes(): void
    {
        $this->get('/chronik')
            ->assertOk()
            ->assertSee('max-w-6xl', false)
            ->assertSee('bg-gray-100', false);
    }

    public function test_member_page_component_renders_for_dashboard(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }
}

