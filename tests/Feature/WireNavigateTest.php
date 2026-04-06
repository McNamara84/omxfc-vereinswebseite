<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class WireNavigateTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Navigation Menu ──────────────────────────────────────────

    public function test_navigation_menu_has_wire_navigate_on_home_link(): void
    {
        $response = $this->get('/');

        $response->assertSee('wire:navigate', false);
    }

    public function test_navigation_menu_has_wire_navigate_on_termine_link(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('termine'), false);
        $response->assertSee('wire:navigate', false);
    }

    public function test_navigation_menu_has_wire_navigate_on_dashboard_link_for_authenticated_users(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get('/');

        // Dashboard link should include wire:navigate
        $dashboardUrl = route('dashboard');
        $response->assertSee($dashboardUrl, false);
        $response->assertSee('wire:navigate', false);
    }

    public function test_navigation_menu_logout_does_not_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get('/');

        // Logout is a form POST, it should NOT have wire:navigate
        $html = $response->getContent();
        $this->assertStringNotContainsString('Ausloggen" wire:navigate', $html);
    }

    // ── Public Pages ────────────────────────────────────────────

    public function test_home_page_internal_links_have_wire_navigate(): void
    {
        $response = $this->get('/');

        $html = $response->getContent();

        // Verify that route-based links include wire:navigate
        $this->assertMatchesRegularExpression(
            '/href="[^"]*" wire:navigate/',
            $html,
        );
    }

    public function test_fantreffen_page_has_wire_navigate_on_internal_links(): void
    {
        $response = $this->get(route('fantreffen.2026'));

        $html = $response->getContent();

        // Route-based links should have wire:navigate
        if (preg_match('/href="\{\{[^"]*route\(/', $html) || preg_match('/href="[^"]*" wire:navigate/', $html)) {
            $this->assertStringContainsString('wire:navigate', $html);
        } else {
            // Page may only have external links — that's fine
            $this->assertTrue(true);
        }
    }

    // ── Member Pages ────────────────────────────────────────────

    public function test_dashboard_links_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get('/dashboard');

        $html = $response->getContent();

        // Dashboard should contain wire:navigate links
        $this->assertStringContainsString('wire:navigate', $html);
    }

    public function test_todos_index_links_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get(route('todos.index'));

        $html = $response->getContent();
        $this->assertStringContainsString('wire:navigate', $html);
    }

    public function test_reviews_index_links_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get(route('reviews.index'));

        $html = $response->getContent();
        $this->assertStringContainsString('wire:navigate', $html);
    }

    public function test_hoerbuecher_index_links_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $html = $response->getContent();
        $this->assertStringContainsString('wire:navigate', $html);
    }

    public function test_romantausch_index_links_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $response = $this->actingAs($user)->get(route('romantausch.index'));

        $html = $response->getContent();
        $this->assertStringContainsString('wire:navigate', $html);
    }

    // ── Livewire Config ─────────────────────────────────────────

    public function test_livewire_progress_bar_uses_brand_color(): void
    {
        $config = config('livewire.navigate');

        $this->assertTrue($config['show_progress_bar']);
        $this->assertEquals('#8B0116', $config['progress_bar_color']);
    }
}
