<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonIdentityTombstone;
use App\Models\Team;
use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use App\Services\Maddraxikon\OAuthClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonOAuthHardeningTestFixtures::class);

beforeEach(function (): void {
    config(['app.url' => 'https://maddrax-fanclub.de', 'maddraxikon.features.linking_enabled' => true, 'maddraxikon.base_url' => 'https://de.maddraxikon.com', 'maddraxikon.api_url' => self::API_URL, 'maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com/rest.php/oauth2/authorize', 'maddraxikon.oauth.token_url' => self::TOKEN_URL, 'maddraxikon.oauth.profile_url' => self::PROFILE_URL, 'maddraxikon.wiki_key' => 'maddraxikon-de', 'maddraxikon.consent_version' => '2026-07-18', 'services.maddraxikon.client_id' => 'client-id', 'services.maddraxikon.client_secret' => 'client-secret', 'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback', 'services.maddraxikon.scope' => 'mwoauth-authonly']);
    Mail::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('former member can disconnect own active link', function (): void {
    $formerMember = $this->createMember(Role::Anwaerter);
    $link = MaddraxikonAccountLink::factory()->for($formerMember)->create();
    $this->actingAs($formerMember)->delete(route('maddraxikon.oauth.disconnect'))->assertRedirect(route('profile.show'))->assertSessionHas('maddraxikon_status');
    $link->refresh();
    expect($link->status)->toBe(MaddraxikonAccountLinkStatus::Disconnected)
        ->and($link->disconnected_at)->not->toBeNull();
});

test('disconnect still requires authentication', function (): void {
    $this->delete(route('maddraxikon.oauth.disconnect'))->assertRedirect(route('login'));
});

test('consent version and time are frozen when oauth starts', function (): void {
    Carbon::setTestNow('2026-07-18 10:00:00');
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    config(['maddraxikon.consent_version' => 'changed-after-start']);
    Carbon::setTestNow('2026-07-18 10:05:00');
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_status');
    $link = MaddraxikonAccountLink::query()->whereBelongsTo($member)->sole();
    expect($link->consent_version)->toBe('2026-07-18')
        ->and($link->consented_at->getTimestamp())->toBe(Carbon::parse('2026-07-18 10:00:00')->getTimestamp())
        ->and($link->verified_at->getTimestamp())->toBe(Carbon::parse('2026-07-18 10:05:00')->getTimestamp());
});

test('eligibility is rechecked after external identity requests', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity(function () use ($member): void {
        Team::membersTeam()?->users()->updateExistingPivot($member->id, ['role' => Role::Anwaerter->value]);
    });
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    Http::assertSentCount(3);
    $this->assertDatabaseMissing('maddraxikon_account_links', ['user_id' => $member->id]);
});

test('deleted club account identity cannot be reclaimed', function (): void {
    $formerOwner = $this->createMember();
    MaddraxikonAccountLink::factory()->for($formerOwner)->create(['oauth_subject' => '42', 'wiki_user_id' => 42, 'wiki_username' => 'Wiki Mitglied']);
    $formerOwner->delete();
    $this->assertDatabaseCount('maddraxikon_identity_tombstones', 1);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    $claimant = $this->createMember();
    $attempt = $this->beginLink($claimant);
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    $this->assertDatabaseMissing('maddraxikon_account_links', ['user_id' => $claimant->id]);
    expect(MaddraxikonIdentityTombstone::query()->count())->toBe(1);
});

test('oauth scope must be exactly identity only', function (): void {
    config(['services.maddraxikon.scope' => 'mwoauth-authonlyprivate']);
    expect(fn () => app(OAuthClient::class)->authorizationUrl(str_repeat('s', 43), str_repeat('c', 43)))->toThrow(OAuthFlowException::class);
});
