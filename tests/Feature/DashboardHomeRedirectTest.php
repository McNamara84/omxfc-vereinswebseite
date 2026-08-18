<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\UpdateLastActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class DashboardHomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_still_sees_public_homepage(): void
    {
        $this->withoutVite()
            ->get('/')
            ->assertOk()
            ->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    }

    public function test_verified_member_is_redirected_from_homepage_to_dashboard(): void
    {
        $member = $this->member(Role::Mitglied);

        $this->actingAs($member)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_unverified_member_still_sees_public_homepage(): void
    {
        $member = $this->member(Role::Mitglied, verified: false);

        $this->withoutVite()
            ->actingAs($member)
            ->get('/')
            ->assertOk()
            ->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    }

    public function test_applicant_is_not_redirected_into_protected_dashboard(): void
    {
        $applicant = $this->member(Role::Anwaerter);

        $this->withoutVite()
            ->actingAs($applicant)
            ->get('/')
            ->assertOk()
            ->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    }

    public function test_user_without_current_team_is_not_redirected(): void
    {
        $user = User::factory()->create(['current_team_id' => Team::membersTeam()->id]);
        $user->setRelation('currentTeam', null);

        $this->withoutVite()
            ->actingAs($user)
            ->get('/')
            ->assertOk();
    }

    public function test_verified_member_without_current_team_cannot_open_dashboard_directly(): void
    {
        $this->withoutMiddleware(UpdateLastActivity::class);

        $user = $this->member(Role::Mitglied);
        $user->forceFill(['current_team_id' => null]);
        $user->setRelation('currentTeam', null);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_user_without_members_team_membership_cannot_enter_a_redirect_loop(): void
    {
        $otherTeam = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $this->withoutVite()
            ->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('home'));
    }

    public function test_navigation_brand_links_eligible_member_to_dashboard(): void
    {
        $member = $this->member(Role::Mitglied);

        $response = $this->actingAs($member)->get('/dashboard');

        $response->assertOk();
        $crawler = new Crawler($response->getContent());
        $brandLink = $crawler->filter('[data-testid="navigation-brand-link"]');

        $this->assertCount(1, $brandLink);
        $this->assertSame(route('dashboard'), $brandLink->attr('href'));
    }

    private function member(Role $role, bool $verified = true): User
    {
        $team = Team::membersTeam();
        $factory = $verified ? User::factory() : User::factory()->unverified();
        $user = $factory->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
