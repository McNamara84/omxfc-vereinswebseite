<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonIdentityTombstone;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use App\Services\Maddraxikon\OAuthClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MaddraxikonOAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://maddrax-fanclub.de',
            'maddraxikon.features.linking_enabled' => true,
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
            'maddraxikon.api_url' => self::API_URL,
            'maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com/rest.php/oauth2/authorize',
            'maddraxikon.oauth.token_url' => self::TOKEN_URL,
            'maddraxikon.oauth.profile_url' => self::PROFILE_URL,
            'maddraxikon.wiki_key' => 'maddraxikon-de',
            'maddraxikon.consent_version' => '2026-07-18',
            'services.maddraxikon.client_id' => 'client-id',
            'services.maddraxikon.client_secret' => 'client-secret',
            'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback',
            'services.maddraxikon.scope' => 'mwoauth-authonly',
        ]);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_former_member_can_disconnect_own_active_link(): void
    {
        $formerMember = $this->createMember(Role::Anwaerter);
        $link = MaddraxikonAccountLink::factory()->for($formerMember)->create();

        $this->actingAs($formerMember)
            ->delete(route('maddraxikon.oauth.disconnect'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('maddraxikon_status');

        $link->refresh();
        $this->assertSame(MaddraxikonAccountLinkStatus::Disconnected, $link->status);
        $this->assertNotNull($link->disconnected_at);
    }

    public function test_disconnect_still_requires_authentication(): void
    {
        $this->delete(route('maddraxikon.oauth.disconnect'))
            ->assertRedirect(route('login'));
    }

    public function test_consent_version_and_time_are_frozen_when_oauth_starts(): void
    {
        Carbon::setTestNow('2026-07-18 10:00:00');
        $member = $this->createMember();
        $attempt = $this->beginLink($member);

        config(['maddraxikon.consent_version' => 'changed-after-start']);
        Carbon::setTestNow('2026-07-18 10:05:00');
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_status');

        $link = MaddraxikonAccountLink::query()->whereBelongsTo($member)->sole();

        $this->assertSame('2026-07-18', $link->consent_version);
        $this->assertSame(
            Carbon::parse('2026-07-18 10:00:00')->getTimestamp(),
            $link->consented_at->getTimestamp(),
        );
        $this->assertSame(
            Carbon::parse('2026-07-18 10:05:00')->getTimestamp(),
            $link->verified_at->getTimestamp(),
        );
    }

    public function test_eligibility_is_rechecked_after_external_identity_requests(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);

        $this->fakeSuccessfulIdentity(function () use ($member): void {
            Team::membersTeam()?->users()->updateExistingPivot($member->id, [
                'role' => Role::Anwaerter->value,
            ]);
        });

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        Http::assertSentCount(3);
        $this->assertDatabaseMissing('maddraxikon_account_links', [
            'user_id' => $member->id,
        ]);
    }

    public function test_deleted_club_account_identity_cannot_be_reclaimed(): void
    {
        $formerOwner = $this->createMember();
        MaddraxikonAccountLink::factory()->for($formerOwner)->create([
            'oauth_subject' => '42',
            'wiki_user_id' => 42,
            'wiki_username' => 'Wiki Mitglied',
        ]);
        $formerOwner->delete();

        $this->assertDatabaseCount('maddraxikon_identity_tombstones', 1);
        $this->assertDatabaseCount('maddraxikon_account_links', 0);

        $claimant = $this->createMember();
        $attempt = $this->beginLink($claimant);
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        $this->assertDatabaseMissing('maddraxikon_account_links', [
            'user_id' => $claimant->id,
        ]);
        $this->assertSame(1, MaddraxikonIdentityTombstone::query()->count());
    }

    public function test_oauth_scope_must_be_exactly_identity_only(): void
    {
        config(['services.maddraxikon.scope' => 'mwoauth-authonlyprivate']);

        $this->expectException(OAuthFlowException::class);

        app(OAuthClient::class)->authorizationUrl(
            str_repeat('s', 43),
            str_repeat('c', 43),
        );
    }

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

                return Http::response([
                    'access_token' => 'access-secret',
                    'token_type' => 'Bearer',
                ]);
            }

            if ($request->url() === self::PROFILE_URL) {
                return Http::response([
                    'sub' => '42',
                    'username' => 'Wiki Mitglied',
                    'blocked' => false,
                ]);
            }

            if (str_starts_with($request->url(), self::API_URL.'?')) {
                return Http::response([
                    'query' => [
                        'users' => [[
                            'userid' => 42,
                            'name' => 'Wiki Mitglied',
                        ]],
                    ],
                ]);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, string>
     */
    private function beginLink(User $member): array
    {
        $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), [
            'consent' => '1',
        ]);

        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    }

    private function createMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create([
            'current_team_id' => $team->id,
            'lat' => 48.0,
            'lon' => 11.0,
        ]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
