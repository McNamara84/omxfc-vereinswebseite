<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait MaddraxikonOAuthTestFixtures
{
    use RefreshDatabase;

    private const AUTHORIZE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/authorize';

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';

    /**
     * @param  array<string, mixed>  $apiExtras
     */
    private function fakeSuccessfulIdentity(mixed $subject = '42', int $apiUserId = 42, string $username = 'Wiki Mitglied', array $apiExtras = []): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret', 'token_type' => 'Bearer']), self::PROFILE_URL => Http::response(['sub' => $subject, 'username' => $username, 'blocked' => false, 'confirmed_email' => true, 'email' => 'must-not-be-used@example.com']), self::API_URL.'*' => Http::response(['query' => ['users' => [array_merge(['userid' => $apiUserId, 'name' => $username], $apiExtras)]]])]);
    }

    /**
     * @return array<string, string>
     */
    private function beginLink(User $member): array
    {
        $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), ['consent' => '1']);
        $response->assertRedirect();

        return $this->redirectQuery($response->headers->get('Location'));
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(?string $url): array
    {
        expect($url)->toBeString();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function assertSecureCallbackHeaders(TestResponse $response): void
    {
        expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        expect($cacheControl)->toContain('no-store')
            ->toContain('private')
            ->toContain('max-age=0')
            ->and($response->headers->get('Pragma'))->toBe('no-cache');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(Role $role = Role::Mitglied, array $attributes = []): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge(['current_team_id' => $team->id, 'lat' => 48.0, 'lon' => 11.0], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
