<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMaddraxikonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_can_view_maddraxikon_page(): void
    {
        Http::fake([
            'https://de.maddraxikon.com/api.php*' => Http::response([
                'parse' => [
                    'text' => '<div class="mw-parser-output"><p>Test Navigation</p></div>',
                ],
            ], 200),
        ]);

        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $response = $this->actingAs($user)->get(route('admin.maddraxikon.index'));

        $response->assertOk();
        $response->assertSee('Navigationsinhalte');
        $response->assertSee('<p>Test Navigation</p>', false);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://de.maddraxikon.com/api.php')
                && $request['page'] === 'Vorlage:Hauptseite/Navigation'
                && (string) $request['formatversion'] === '2';
        });
    }

    public function test_non_admin_users_cannot_access_maddraxikon_page(): void
    {
        Http::fake();

        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get(route('admin.maddraxikon.index'));

        $response->assertForbidden();
        Http::assertNotSent(function (Request $request) {
            return $request->url() === 'https://de.maddraxikon.com/api.php';
        });
    }

    public function test_error_message_is_shown_when_mediawiki_request_fails(): void
    {
        Http::fake([
            'https://de.maddraxikon.com/api.php*' => Http::response([], 500),
        ]);

        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $response = $this->actingAs($user)->get(route('admin.maddraxikon.index'));

        $response->assertOk();
        $response->assertSee('Laden fehlgeschlagen');
        $response->assertSee('Der Inhalt des Maddraxikon konnte aktuell nicht geladen werden', false);
    }
}
