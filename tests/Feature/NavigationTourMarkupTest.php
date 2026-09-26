<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
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

    public function test_main_navigation_tour_uses_version_six_and_shared_sidebar_selectors(): void
    {
        $tour = config('tours.hauptmenue');

        $this->assertSame(6, $tour['version']);
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

    #[TestWith([Role::Mitglied, false, false])]
    #[TestWith([Role::Mitglied, true, true])]
    #[TestWith([Role::Admin, false, true])]
    public function test_character_tour_anchor_matches_visible_navigation_and_reveal_targets(Role $role, bool $inRpgTeam, bool $visible): void
    {
        $members = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $members->id]);
        $members->users()->attach($user, ['role' => $role->value]);
        if ($inRpgTeam) {
            $rpg = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
            $rpg->users()->attach($user, ['role' => Role::Mitglied->value]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $crawler = new Crawler($response->getContent());
        $steps = collect(config('tours.hauptmenue.steps'));
        $step = $steps->firstWhere('key', 'teams-characters');
        $this->assertNotNull($step);
        $this->assertSame('Meine Charaktere', $step['title']);
        $keys = $steps->pluck('key')->all();
        $this->assertSame(array_search('teams-char-editor', $keys, true) + 1, array_search('teams-characters', $keys, true));

        foreach (['desktop', 'mobile'] as $device) {
            $anchor = $crawler->filter($step['selectors'][$device]);
            $this->assertCount($visible ? 1 : 0, $anchor);
            if ($visible) {
                $this->assertSame(route('rpg.characters.index'), $anchor->attr('href'));
                $this->assertSame('Meine Charaktere', trim($anchor->text()));
                foreach ($step['reveal'][$device] as $selector) {
                    $this->assertCount(1, $crawler->filter($selector), $selector);
                }
            }
        }
        $this->assertSame(['[data-tour-key="section-teams"]'], $step['reveal']['desktop']);
        $this->assertSame(['[data-tour-key="mobile-menu-toggle"]', '[data-tour-key="section-teams"]'], $step['reveal']['mobile']);
    }
}
