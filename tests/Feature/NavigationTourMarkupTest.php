<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class NavigationTourMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_navigation_renders_tour_runner_and_navigation_anchors(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="tour-runner-root"', false)
            ->assertSee('id="tour-runner-skip"', false)
            ->assertSee('id="tour-runner-back"', false)
            ->assertSee('id="tour-runner-next"', false)
            ->assertSee('id="tour-runner-complete"', false)
            ->assertSee('data-tour-key="dashboard"', false)
            ->assertSee('data-tour-key="section-community"', false)
            ->assertSee('data-tour-key="profile-menu"', false)
            ->assertSee('data-tour-key="profile-settings"', false)
            ->assertSee('data-tour-key="mobile-menu-toggle"', false);

        $crawler = new Crawler($response->getContent());

        $this->assertCount(0, $crawler->filter('nav summary button'));
        $this->assertCount(0, $crawler->filter('nav summary [data-tour-key][aria-expanded]'));
        $this->assertCount(1, $crawler->filter('[data-tour-key="section-community"] > details > summary'));
        $this->assertSame('show ? \'true\' : \'false\'', $crawler->filter('[data-tour-key="section-community"]')->attr('x-bind:data-tour-open'));
    }

    public function test_main_navigation_tour_uses_version_five_and_shared_sidebar_selectors(): void
    {
        $tour = config('tours.hauptmenue');

        $this->assertSame(5, $tour['version']);
        $this->assertStringContainsString('Sidebar', $tour['description']);

        foreach ($tour['steps'] as $step) {
            foreach (($step['selectors'] ?? []) as $selector) {
                $this->assertStringNotContainsString('data-tour-device', $selector);
            }

            foreach (($step['reveal'] ?? []) as $selectors) {
                foreach ($selectors as $selector) {
                    $this->assertStringNotContainsString('data-tour-device', $selector);
                }
            }
        }

        $profileStep = collect($tour['steps'])->firstWhere('key', 'profile-settings');
        $this->assertSame(
            ['[data-tour-key="profile-menu"]'],
            $profileStep['reveal']['mobile'] ?? null,
        );
    }
}
