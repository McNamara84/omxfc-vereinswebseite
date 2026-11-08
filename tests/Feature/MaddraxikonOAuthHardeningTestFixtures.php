<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

trait MaddraxikonOAuthHardeningTestFixtures
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';

    /**
     * @param  null|callable(): void  $beforeTokenResponse
     */
    private function fakeSuccessfulIdentity(?callable $beforeTokenResponse = null): void
    {
        Http::fake(function (ClientRequest $request) use ($beforeTokenResponse) {
            if ($request->url() === self::TOKEN_URL) {
                if ($beforeTokenResponse) {
                    $beforeTokenResponse();
                }

                return Http::response(['access_token' => 'access-secret', 'token_type' => 'Bearer']);
            }
            if ($request->url() === self::PROFILE_URL) {
                return Http::response(['sub' => '42', 'username' => 'Wiki Mitglied', 'blocked' => false]);
            }
            if (str_starts_with($request->url(), self::API_URL.'?')) {
                return Http::response(['query' => ['users' => [['userid' => 42, 'name' => 'Wiki Mitglied']]]]);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, string>
     */
    private function beginLink(User $member): array
    {
        $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), ['consent' => '1']);
        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    }

    private function createMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id, 'lat' => 48.0, 'lon' => 11.0]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
