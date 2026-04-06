<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class WireNavigateTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * Prüft, dass ein konkreter href-Wert zusammen mit wire:navigate im selben Tag auftaucht.
     */
    private function assertLinkHasWireNavigate(string $html, string $url, string $context = ''): void
    {
        $escaped = preg_quote($url, '/');
        // Entweder href="URL" ... wire:navigate oder wire:navigate ... href="URL"
        $pattern = '/<(a|button)[^>]*href="'.$escaped.'"[^>]*wire:navigate/s';
        $patternReverse = '/<(a|button)[^>]*wire:navigate[^>]*href="'.$escaped.'"/s';
        $this->assertTrue(
            (bool) preg_match($pattern, $html) || (bool) preg_match($patternReverse, $html),
            "Link zu {$url} sollte wire:navigate haben".($context ? " ({$context})" : ''),
        );
    }

    /**
     * Prüft, dass ein konkreter href-Wert KEIN wire:navigate hat.
     */
    private function assertLinkHasNoWireNavigate(string $html, string $url, string $context = ''): void
    {
        $escaped = preg_quote($url, '/');
        $pattern = '/<(a|button)[^>]*href="'.$escaped.'"[^>]*wire:navigate/s';
        $patternReverse = '/<(a|button)[^>]*wire:navigate[^>]*href="'.$escaped.'"/s';
        $this->assertFalse(
            (bool) preg_match($pattern, $html) || (bool) preg_match($patternReverse, $html),
            "Link zu {$url} sollte KEIN wire:navigate haben".($context ? " ({$context})" : ''),
        );
    }

    // ── Navigation Menu ──────────────────────────────────────────

    public function test_navigation_menu_has_wire_navigate_on_home_link(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertLinkHasWireNavigate($html, route('home'), 'Home-Link');
    }

    public function test_navigation_menu_has_wire_navigate_on_termine_link(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $html = $this->actingAs($user)->get('/')->getContent();

        $this->assertLinkHasWireNavigate($html, route('termine'), 'Termine-Link');
    }

    public function test_navigation_menu_has_wire_navigate_on_dashboard_link_for_authenticated_users(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get('/')->getContent();

        $this->assertLinkHasWireNavigate($html, route('dashboard'), 'Dashboard-Link');
    }

    public function test_navigation_menu_logout_does_not_have_wire_navigate(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get('/')->getContent();

        // Logout-Formular extrahieren und sicherstellen, dass kein wire:navigate darin vorkommt
        preg_match('/<form[^>]*logout[^>]*>.*?<\/form>/si', $html, $logoutForm);
        $this->assertNotEmpty($logoutForm, 'Logout-Formular sollte vorhanden sein');
        $this->assertStringNotContainsString('wire:navigate', $logoutForm[0]);
    }

    // ── Public Pages ────────────────────────────────────────────

    public function test_home_page_has_wire_navigate_on_chronik_link(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertLinkHasWireNavigate($html, route('chronik'), 'Chronik-Link');
    }

    public function test_home_page_has_wire_navigate_on_mitglied_werden_link(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertLinkHasWireNavigate($html, route('mitglied.werden'), 'Mitglied-werden-Link');
    }

    // ── Member Pages ────────────────────────────────────────────

    public function test_dashboard_has_wire_navigate_on_todos_link(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertLinkHasWireNavigate($html, route('todos.index'), 'Todos-Link');
    }

    public function test_dashboard_has_wire_navigate_on_romantausch_link(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertLinkHasWireNavigate($html, route('romantausch.index'), 'Romantausch-Link');
    }

    public function test_todos_index_has_wire_navigate_on_create_link(): void
    {
        $user = $this->actingVorstand();

        $html = $this->actingAs($user)->get(route('todos.index'))->getContent();

        $this->assertLinkHasWireNavigate($html, route('todos.create'), 'Todo-erstellen-Link');
    }

    public function test_reviews_index_has_wire_navigate_on_navigation_links(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get(route('reviews.index'))->getContent();

        // Die Seite enthält mindestens einen wire:navigate-Link (z.B. im Navigationsmenü)
        $this->assertLinkHasWireNavigate($html, route('home'), 'Home-Link in Reviews-Navigation');
    }

    public function test_hoerbuecher_index_has_wire_navigate_in_navigation(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get(route('hoerbuecher.index'))->getContent();

        $this->assertLinkHasWireNavigate($html, route('dashboard'), 'Dashboard-Link in Hörbücher-Navigation');
    }

    public function test_romantausch_index_has_wire_navigate_on_offer_link(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get(route('romantausch.index'))->getContent();

        $this->assertLinkHasWireNavigate($html, route('romantausch.create-offer'), 'Angebot-erstellen-Link');
    }

    // ── Downloads: kein wire:navigate ────────────────────────────

    public function test_downloads_page_does_not_have_wire_navigate_on_download_buttons(): void
    {
        $user = $this->actingMember();

        $html = $this->actingAs($user)->get(route('downloads'))->getContent();

        // Seite lädt erfolgreich
        $this->assertNotEmpty($html);

        // Download-Links dürfen KEIN wire:navigate haben (Binary-Response)
        if (preg_match_all('/href="([^"]*herunterladen\/[^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $downloadUrl) {
                $this->assertLinkHasNoWireNavigate($html, $downloadUrl, 'Download-Button');
            }
        }
    }

    // ── Livewire Config ─────────────────────────────────────────

    public function test_livewire_progress_bar_uses_brand_color(): void
    {
        $config = config('livewire.navigate');

        $this->assertTrue($config['show_progress_bar']);
        $this->assertEquals('#8B0116', $config['progress_bar_color']);
    }
}
