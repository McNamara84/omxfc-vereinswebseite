<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\UpdateLastActivity;
use App\Models\Team;
use App\Models\User;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DashboardHomeRedirectTestFixtures::class);

test('guest still sees public homepage', function (): void {
    $this->withoutVite()->get('/')->assertOk()->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
});

test('verified member is redirected from homepage to dashboard', function (): void {
    $member = $this->member(Role::Mitglied);
    $this->actingAs($member)->get('/')->assertRedirect(route('dashboard'));
});

test('member without email confirmation can enter dashboard and explicitly visit public homepage', function (): void {
    $member = $this->member(Role::Mitglied, verified: false);
    $this->withoutVite()->actingAs($member)->get('/')->assertRedirect(route('dashboard'));
    $this->get('/dashboard')->assertOk();
    $this->get('/?ansicht=oeffentlich')->assertOk()->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    expect($member->fresh()->email_verified_at)->toBeNull();
});

test('applicant is not redirected into protected dashboard', function (): void {
    $applicant = $this->member(Role::Anwaerter);
    $this->withoutVite()->actingAs($applicant)->get('/')->assertOk()->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
});

test('user without current team is not redirected', function (): void {
    $user = User::factory()->create(['current_team_id' => Team::membersTeam()->id]);
    $user->setRelation('currentTeam', null);
    $this->withoutVite()->actingAs($user)->get('/')->assertOk();
});

test('verified member without current team cannot open dashboard directly', function (): void {
    $this->withoutMiddleware(UpdateLastActivity::class);
    $user = $this->member(Role::Mitglied);
    $user->forceFill(['current_team_id' => null]);
    $user->setRelation('currentTeam', null);
    $this->actingAs($user)->get('/dashboard')->assertForbidden();
});

test('user without members team membership cannot enter a redirect loop', function (): void {
    $otherTeam = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $this->withoutVite()->actingAs($user)->get('/')->assertOk()->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('home'));
});

test('navigation brand links eligible member to dashboard', function (): void {
    $member = $this->member(Role::Mitglied);
    $response = $this->actingAs($member)->get('/dashboard');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $brandLink = $crawler->filter('[data-testid="member-navigation-brand-link"]');
    expect($brandLink)->toHaveCount(1)
        ->and($brandLink->attr('href'))->toBe(route('dashboard'));
});
