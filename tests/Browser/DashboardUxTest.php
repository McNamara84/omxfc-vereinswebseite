<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;

it('zeigt das kompakte dashboard responsiv und lädt den feed automatisch nach', function () {
    $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder']);
    $member = User::factory()->create([
        'current_team_id' => $team->id,
        'email_verified_at' => now(),
        'vorname' => 'Mara',
    ]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);

    foreach (range(1, 18) as $minutesAgo) {
        Activity::query()->create([
            'user_id' => $member->id,
            'subject_type' => User::class,
            'subject_id' => $member->id,
            'action' => 'member_approved',
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    $this->actingAs($member->refresh());

    $page = visit('/', ['waitUntil' => 'domcontentloaded'])
        ->inLightMode()
        ->resize(1440, 1000)
        ->waitForText('Jetzt wichtig')
        ->assertPathIs('/dashboard')
        ->assertSee('Willkommen zurück, Mara')
        ->assertSee('Zu erledigen')
        ->assertSee('Mein Fortschritt')
        ->assertSee('Community')
        ->assertVisible('[data-testid="dashboard-metric-group-important"]')
        ->assertVisible('[data-testid="dashboard-metric-group-progress"]')
        ->assertVisible('[data-testid="dashboard-metric-group-community"]')
        ->assertVisible('[data-testid="activity-filters"]')
        ->assertVisible('[data-dashboard-feed-load-more]')
        ->assertScript('document.querySelectorAll("[data-testid^=dashboard-metric-]:not([data-testid^=dashboard-metric-group-])").length', 12)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(true, 'dashboard-redesign-desktop');

    $page->script('document.querySelector("[data-dashboard-feed-sentinel]")?.scrollIntoView({ block: "center" })');
    $page->waitForText('Ende des Aktivitätsverlaufs')
        ->assertScript('document.querySelectorAll("[data-testid=dashboard-activity]").length', 18)
        ->assertNoJavaScriptErrors();

    $page->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertVisible('[data-testid="dashboard-metric-group-important"]')
        ->assertVisible('[data-testid="activity-filters"]')
        ->assertNoJavaScriptErrors()
        ->screenshot(true, 'dashboard-redesign-mobile');
});
