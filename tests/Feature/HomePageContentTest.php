<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_all_sections_and_projects(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
            ->assertSee('<title>Startseite – Offizieller MADDRAX Fanclub e. V.</title>', false)
            ->assertSee('Wer wir sind')
            ->assertSee('Wir Maddrax-Fans sind eine muntere Gruppe')
            ->assertSee('Was wir machen')
            ->assertSee('Wir treffen uns in unterschiedlichen Konstellationen mal online')
            ->assertSee('Aktuelle Projekte')
            ->assertSee('Maddraxikon')
            ->assertSee('EARDRAX')
            ->assertSee('MAPDRAX')
            ->assertSee('Fantreffen 2026')
            ->assertSee('Vorteile einer Mitgliedschaft')
            ->assertSee('Kostenlose Teilnahme an den jährlichen Fantreffen')
            ->assertSee('aktive Mitglieder');
    }

    public function test_home_page_displays_member_and_review_metrics(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $members = User::factory()->count(3)->create();

        $team->users()->attach(
            $members->pluck('id'),
            ['role' => Role::Mitglied->value]
        );

        $book = Book::factory()->create();

        Review::factory()->count(4)->create([
            'team_id' => $team->id,
            'user_id' => $members->first()->id,
            'book_id' => $book->id,
        ]);

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('aria-label="3 aktive Mitglieder"', false)
            ->assertSee('aria-label="4 Rezensionen"', false)
            ->assertSee('Rezensionen');
    }

    public function test_home_page_contains_structured_data(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('@context', false)
            ->assertSee('SearchAction', false);
    }
}
