<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class MitgliederIndexAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create([
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_members_table_summary_reflects_sort_and_filters(): void
    {
        $admin = $this->createMember(Role::Admin);
        $team = Team::membersTeam();

        $onlineMember = User::factory()->create([
            'current_team_id' => $team->id,
            'name' => 'Online Mitglied',
        ]);
        $team->users()->attach($onlineMember, ['role' => Role::Mitglied->value]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $onlineMember->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/mitglieder?sort=role&dir=desc&filters[]=online');
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('Mitgliederliste, sortiert nach Rolle in absteigender Reihenfolge.', $html);
        $this->assertStringContainsString('Es werden nur Mitglieder angezeigt, die aktuell online sind.', $html);
        $this->assertMatchesRegularExpression('/Insgesamt sind \d+ Mitglied(?:er)? sichtbar\./', $html);
    }

    public function test_members_heading_exposes_data_attribute_for_ui_tests(): void
    {
        $admin = $this->createMember(Role::Admin);
        $this->actingAs($admin);

        $response = $this->get('/mitglieder');
        $response->assertOk();

        $crawler = new Crawler($response->getContent());
        $heading = $crawler->filter('[data-members-heading]')->first();

        $this->assertSame('Mitgliederliste', trim($heading->text()));
        $this->assertSame('H2', strtoupper($heading->nodeName()));
    }

    public function test_table_headers_expose_aria_sort_attributes(): void
    {
        $admin = $this->createMember(Role::Admin);
        $this->actingAs($admin);

        $response = $this->get('/mitglieder?sort=mitglied_seit&dir=desc');
        $response->assertOk();

        $crawler = new Crawler($response->getContent());

        $mitgliedSeitHeader = $crawler->filter('[data-members-sort-column="mitglied_seit"]')->first();
        $this->assertSame('descending', $mitgliedSeitHeader->attr('aria-sort'));
        $this->assertSame('col', $mitgliedSeitHeader->attr('scope'));

        $nameHeader = $crawler->filter('[data-members-sort-column="nachname"]')->first();
        $this->assertSame('none', $nameHeader->attr('aria-sort'));
    }

    public function test_members_table_includes_dataset_metadata(): void
    {
        $admin = $this->createMember(Role::Admin);
        $team = Team::membersTeam();

        $team->users()->attach(User::factory()->create(['current_team_id' => $team->id]), ['role' => Role::Mitglied->value]);
        $team->users()->attach(User::factory()->create(['current_team_id' => $team->id]), ['role' => Role::Mitglied->value]);

        $this->actingAs($admin);

        $response = $this->get('/mitglieder');
        $response->assertOk();

        $crawler = new Crawler($response->getContent());
        $table = $crawler->filter('[data-members-table]')->first();

        $this->assertSame('nachname', $table->attr('data-members-sort'));
        $this->assertSame('asc', $table->attr('data-members-dir'));
        $this->assertSame(
            (string) $team->activeUsers()->count(),
            $table->attr('data-members-total')
        );
        $this->assertSame('members-table-summary', $table->attr('data-members-summary-id'));
    }
}
