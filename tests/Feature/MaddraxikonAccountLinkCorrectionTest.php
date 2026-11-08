<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Livewire\MaddraxikonAdmin;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonAccountLinkCorrection;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonIdentityTombstone;
use App\Models\MaddraxikonRewardEvent;
use App\Services\Maddraxikon\AccountLinkService;
use App\Services\Maddraxikon\Exceptions\AccountLinkConflictException;
use App\Services\Maddraxikon\MaddraxikonIdentity;
use App\Support\MaddraxikonIdentityHmacPeppers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.testing_minimal_layout', true);
});

test('migration exposes the immutable audit fields', function (): void {
    expect(Schema::hasColumns('maddraxikon_account_link_corrections', ['actor_user_id', 'affected_user_id', 'released_account_link_id', 'wiki_key', 'old_oauth_subject_hash', 'old_wiki_user_id', 'old_wiki_username', 'reason', 'corrected_at']))->toBeTrue()
        ->and(Schema::hasColumn('maddraxikon_account_link_corrections', 'updated_at'))->toBeFalse();
});

test('tombstone records the primary hash key version', function (): void {
    config()->set('maddraxikon.identity_hmac_peppers', ['v2' => str_repeat('n', 32), 'v1' => str_repeat('o', 32)]);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['oauth_subject' => 'versioned-subject', 'wiki_user_id' => 442211]);
    $tombstone = MaddraxikonIdentityTombstone::retire($link);
    expect($tombstone->hash_key_version)->toBe('v2')
        ->and($tombstone->hash_key_fingerprint)->toBe(MaddraxikonIdentityHmacPeppers::fingerprint($link->wiki_key, 'v2', str_repeat('n', 32)));
    $this->assertDatabaseHas('maddraxikon_identity_tombstones', ['id' => $tombstone->id, 'hash_key_version' => 'v2', 'hash_key_fingerprint' => $tombstone->hash_key_fingerprint, 'oauth_subject_hash' => MaddraxikonIdentityTombstone::oauthSubjectHash($link->wiki_key, $link->oauth_subject)]);
});

test('reusing a stored version with another secret fails closed', function (): void {
    Mail::fake();
    $oldSecret = str_repeat('o', 32);
    config()->set('maddraxikon.identity_hmac_peppers', ['stable-version' => $oldSecret]);
    $historicalLink = MaddraxikonAccountLink::factory()->disconnected()->create(['oauth_subject' => 'historical-subject', 'wiki_user_id' => 551901]);
    $tombstone = MaddraxikonIdentityTombstone::retire($historicalLink);
    $historicalLink->delete();
    expect($tombstone->hash_key_fingerprint)->toBe(MaddraxikonIdentityHmacPeppers::fingerprint('maddraxikon-de', 'stable-version', $oldSecret));
    config()->set('maddraxikon.identity_hmac_peppers', ['stable-version' => str_repeat('n', 32)]);
    try {
        app(AccountLinkService::class)->activate($this->createUserWithRole(Role::Mitglied), new MaddraxikonIdentity(oauthSubject: 'unrelated-new-subject', wikiUserId: 551902, wikiUsername: 'NeuesKonto'), 'same-version-secret-change', now());
        $this->fail('A changed secret under an established version was accepted.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('Versionsnamen dürfen')
            ->and($exception->getMessage())->toContain('stable-version');
    }
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Mail::assertNothingQueued();
});

test('tombstone created with previous pepper blocks identity after rotation', function (): void {
    Mail::fake();
    $oldPepper = str_repeat('o', 32);
    $newPepper = str_repeat('n', 32);
    config()->set('maddraxikon.identity_hmac_peppers', ['v1' => $oldPepper]);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['oauth_subject' => 'identity-before-rotation', 'wiki_user_id' => 553311, 'wiki_username' => 'VorDerRotation']);
    $oldHash = MaddraxikonIdentityTombstone::retire($link)->oauth_subject_hash;
    $link->delete();
    config()->set('maddraxikon.identity_hmac_peppers', ['v2' => $newPepper, 'v1' => $oldPepper]);
    expect(MaddraxikonIdentityTombstone::oauthSubjectHash('maddraxikon-de', 'identity-before-rotation'))->not->toBe($oldHash);
    try {
        app(AccountLinkService::class)->activate($this->createUserWithRole(Role::Mitglied), new MaddraxikonIdentity(oauthSubject: 'identity-before-rotation', wikiUserId: 553311, wikiUsername: 'VorDerRotation'), 'rotation-test', now());
        $this->fail('A tombstone using a previous pepper must still block the identity.');
    } catch (AccountLinkConflictException) {
        $this->addToAssertionCount(1);
    }
    expect(MaddraxikonIdentityTombstone::oauthSubjectHashes('maddraxikon-de', 'identity-before-rotation'))->toContain($oldHash);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Mail::assertNothingQueued();
});

test('removing historical pepper blocks all new links', function (): void {
    Mail::fake();
    config()->set('maddraxikon.identity_hmac_peppers', ['v1' => str_repeat('o', 32)]);
    $historicalLink = MaddraxikonAccountLink::factory()->disconnected()->create(['oauth_subject' => 'historical-pepper-subject', 'wiki_user_id' => 597311]);
    MaddraxikonIdentityTombstone::retire($historicalLink);
    $historicalLink->delete();
    config()->set('maddraxikon.identity_hmac_peppers', ['v2' => str_repeat('n', 32)]);
    try {
        app(AccountLinkService::class)->activate($this->createUserWithRole(Role::Mitglied), new MaddraxikonIdentity(oauthSubject: 'entirely-new-subject', wikiUserId: 597312, wikiUsername: 'VölligNeuesKonto'), 'missing-pepper-test', now());
        $this->fail('Linking must fail while a stored hash-key version is missing.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('HMAC-Schlüsselversionen')
            ->and($exception->getMessage())->toContain('v1');
    }
    $this->assertDatabaseHas('maddraxikon_identity_tombstones', ['hash_key_version' => 'v1']);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Mail::assertNothingQueued();
});

test('legacy app key tombstone can be checked during migration', function (): void {
    Mail::fake();
    $legacyAppKey = 'base64:'.base64_encode(random_bytes(32));
    config()->set('app.key', $legacyAppKey);
    $wikiKey = 'maddraxikon-de';
    $oauthSubject = 'legacy-app-key-subject';
    $wikiUserId = 618244;
    $legacySubjectHash = hash_hmac('sha256', implode("\x00", [$wikiKey, 'oauth-subject', $oauthSubject]), $legacyAppKey);
    $legacyWikiUserIdHash = hash_hmac('sha256', implode("\x00", [$wikiKey, 'wiki-user-id', (string) $wikiUserId]), $legacyAppKey);
    MaddraxikonIdentityTombstone::query()->create(['wiki_key' => $wikiKey, 'hash_key_version' => 'legacy-app-key', 'hash_key_fingerprint' => MaddraxikonIdentityHmacPeppers::fingerprint($wikiKey, 'legacy-app-key', $legacyAppKey), 'oauth_subject_hash' => $legacySubjectHash, 'wiki_user_id_hash' => $legacyWikiUserIdHash, 'retired_at' => now()]);
    config()->set('maddraxikon.identity_hmac_peppers', ['v1' => str_repeat('n', 32), 'legacy-app-key' => 'raw:'.$legacyAppKey]);
    expect(MaddraxikonIdentityTombstone::oauthSubjectHash($wikiKey, $oauthSubject))->not->toBe($legacySubjectHash)
        ->and(MaddraxikonIdentityTombstone::oauthSubjectHashes($wikiKey, $oauthSubject))->toContain($legacySubjectHash);
    try {
        app(AccountLinkService::class)->activate($this->createUserWithRole(Role::Mitglied), new MaddraxikonIdentity(oauthSubject: $oauthSubject, wikiUserId: $wikiUserId, wikiUsername: 'HistorischeIdentität'), 'legacy-app-key-test', now());
        $this->fail('A tombstone hashed with the former APP_KEY must still block.');
    } catch (AccountLinkConflictException) {
        $this->addToAssertionCount(1);
    }
    $this->assertDatabaseHas('maddraxikon_identity_tombstones', ['oauth_subject_hash' => $legacySubjectHash, 'hash_key_version' => 'legacy-app-key']);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Mail::assertNothingQueued();
});

test('oauth link conflict is logged without raw identity data', function (): void {
    Mail::fake();
    $owner = $this->createUserWithRole(Role::Mitglied);
    $claimant = $this->createUserWithRole(Role::Mitglied);
    MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $owner->id, 'oauth_subject' => 'raw-sensitive-oauth-subject', 'wiki_user_id' => 664422, 'wiki_username' => 'RawSensitiveUsername']);
    Log::spy();
    try {
        app(AccountLinkService::class)->activate($claimant, new MaddraxikonIdentity(oauthSubject: 'raw-sensitive-oauth-subject', wikiUserId: 664422, wikiUsername: 'RawSensitiveUsername'), 'logging-test', now());
        $this->fail('A claimed identity must result in a conflict.');
    } catch (AccountLinkConflictException) {
        $this->addToAssertionCount(1);
    }
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($claimant): bool {
        expect($message)->toBe('Maddraxikon OAuth account-link conflict.')
            ->and(array_keys($context))->toBe(['event', 'reason', 'user_id', 'wiki_key', 'oauth_subject_fingerprint', 'wiki_user_id_fingerprint'])
            ->and($context['reason'])->toBe('identity_already_claimed')
            ->and($context['user_id'])->toBe($claimant->id)
            ->and(array_values($context))->not->toContain('raw-sensitive-oauth-subject')
            ->and(array_values($context))->not->toContain('RawSensitiveUsername')
            ->and(array_values($context))->not->toContain(664422);

        return true;
    });
});

test('identity hashing fails closed without a dedicated pepper', function (): void {
    config()->set('maddraxikon.identity_hmac_peppers', []);
    expect(fn () => MaddraxikonIdentityTombstone::oauthSubjectHash('maddraxikon-de', 'must-not-fall-back-to-app-key'))->toThrow(LogicException::class, 'MADDRAXIKON_IDENTITY_HMAC_PEPPERS');
});

test('identity hash is stable when laravel app key changes', function (): void {
    config()->set('maddraxikon.identity_hmac_peppers', ['v1' => str_repeat('p', 32)]);
    config()->set('app.key', 'base64:first-unrelated-laravel-app-key');
    $before = MaddraxikonIdentityTombstone::oauthSubjectHash('maddraxikon-de', 'stable-subject');
    config()->set('app.key', 'base64:second-unrelated-laravel-app-key');
    $after = MaddraxikonIdentityTombstone::oauthSubjectHash('maddraxikon-de', 'stable-subject');
    expect($after)->toBe($before)
        ->toBe(hash_hmac('sha256', implode("\x00", ['maddraxikon-de', 'oauth-subject', 'stable-subject']), str_repeat('p', 32)));
});

test('base64 pepper is decoded before hashing', function (): void {
    $pepper = random_bytes(32);
    config()->set('maddraxikon.identity_hmac_peppers', ['v1' => 'base64:'.base64_encode($pepper)]);
    $hash = MaddraxikonIdentityTombstone::wikiUserIdHash('maddraxikon-de', 775533);
    expect($hash)->toBe(hash_hmac('sha256', implode("\x00", ['maddraxikon-de', 'wiki-user-id', '775533']), $pepper));
});

test('legacy app key cannot be the primary pepper', function (): void {
    config()->set('maddraxikon.identity_hmac_peppers', ['legacy-app-key' => 'raw:base64:'.base64_encode(random_bytes(32)), 'v1' => str_repeat('n', 32)]);
    expect(fn () => MaddraxikonIdentityTombstone::oauthSubjectHash('maddraxikon-de', 'legacy-must-not-write-new-hashes'))->toThrow(LogicException::class, 'Legacy-APP_KEY darf nicht der primäre Identitäts-Pepper sein');
});

test('admin correction is audited and preserves source snapshots', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id, 'oauth_subject' => 'wrong-opaque-oauth-subject', 'wiki_user_id' => 71337, 'wiki_username' => 'FalschZugeordnet']);
    $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username]);
    $rewardEvent = MaddraxikonRewardEvent::factory()->create(['account_link_id' => $link->id, 'source_contribution_id' => $contribution->id, 'user_id' => $member->id, 'source_revision_id' => $contribution->revision_id, 'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward]);
    $correction = app(AccountLinkService::class)->releaseDisconnectedLink($admin, $link, 'Mitglied und Wiki-Konto waren dauerhaft falsch zugeordnet.');
    $this->assertDatabaseMissing('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseHas('maddraxikon_identity_tombstones', ['wiki_key' => $link->wiki_key, 'oauth_subject_hash' => MaddraxikonIdentityTombstone::oauthSubjectHash($link->wiki_key, $link->oauth_subject), 'wiki_user_id_hash' => MaddraxikonIdentityTombstone::wikiUserIdHash($link->wiki_key, $link->wiki_user_id)]);
    expect($correction->actor_user_id)->toBe($admin->id)
        ->and($correction->affected_user_id)->toBe($member->id)
        ->and($correction->released_account_link_id)->toBe($link->id)
        ->and($correction->wiki_key)->toBe($link->wiki_key)
        ->and($correction->old_wiki_user_id)->toBe($link->wiki_user_id)
        ->and($correction->old_wiki_username)->toBe($link->wiki_username)
        ->and($correction->reason)->toBe('Mitglied und Wiki-Konto waren dauerhaft falsch zugeordnet.')
        ->and($correction->old_oauth_subject_hash)->toBe(MaddraxikonIdentityTombstone::oauthSubjectHash($link->wiki_key, $link->oauth_subject))->not->toBe($link->oauth_subject)
        ->and($correction->actor->is($admin))->toBeTrue()
        ->and($correction->affectedUser->is($member))->toBeTrue();
    $contribution->refresh();
    $rewardEvent->refresh();
    expect($contribution->account_link_id)->toBeNull()
        ->and($contribution->wiki_username)->toBe('FalschZugeordnet')
        ->and($contribution->wiki_user_id)->toBe(71337)
        ->and($rewardEvent->account_link_id)->toBeNull()
        ->and($rewardEvent->source_contribution_id)->toBe($contribution->id);
});

test('only admins may release a disconnected link', function (): void {
    $actor = $this->createUserWithRole(Role::Mitglied);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id]);
    try {
        app(AccountLinkService::class)->releaseDisconnectedLink($actor, $link, 'Nicht autorisierter Versuch');
        $this->fail('A non-admin correction must be rejected.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('Nur Administratoren');
    }
    $this->assertDatabaseHas('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseCount('maddraxikon_account_link_corrections', 0);
    $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
});

test('active link cannot be released', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id]);
    try {
        app(AccountLinkService::class)->releaseDisconnectedLink($admin, $link, 'Aktive Verbindung darf nicht gelöscht werden');
        $this->fail('An active link must not be released.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('bereits getrennte');
    }
    $this->assertDatabaseHas('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseCount('maddraxikon_account_link_corrections', 0);
    $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
});

test('service requires a reason before mutating data', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id]);
    $this->expectException(InvalidArgumentException::class);
    try {
        app(AccountLinkService::class)->releaseDisconnectedLink($admin, $link, '   ');
    } finally {
        $this->assertDatabaseHas('maddraxikon_account_links', ['id' => $link->id]);
        $this->assertDatabaseCount('maddraxikon_account_link_corrections', 0);
        $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
    }
});

test('audit failure rolls back tombstone and link deletion', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id]);
    $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username]);
    MaddraxikonAccountLinkCorrection::creating(static fn () => throw new RuntimeException('Audit write failed'));
    try {
        app(AccountLinkService::class)->releaseDisconnectedLink($admin, $link, 'Dieser Versuch wird vollständig zurückgerollt.');
        $this->fail('The injected audit failure must bubble up.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Audit write failed');
    }
    $this->assertDatabaseHas('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $contribution->id, 'account_link_id' => $link->id]);
    $this->assertDatabaseCount('maddraxikon_account_link_corrections', 0);
    $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
});

test('correction audit cannot be changed or deleted', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id]);
    $correction = app(AccountLinkService::class)->releaseDisconnectedLink($admin, $link, 'Unveränderlicher Originalgrund');
    try {
        $correction->forceFill(['reason' => 'Manipulierter Grund'])->save();
        $this->fail('Audit updates must be rejected.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('unveränderlich');
    }
    $correction->refresh();
    expect($correction->reason)->toBe('Unveränderlicher Originalgrund');
    try {
        $correction->delete();
        $this->fail('Audit deletion must be rejected.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('nicht gelöscht');
    }
    $this->assertDatabaseHas('maddraxikon_account_link_corrections', ['id' => $correction->id, 'reason' => 'Unveränderlicher Originalgrund']);
});

test('old identity stays blocked but member can relink a new oauth identity', function (): void {
    Mail::fake();
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    $otherMember = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id, 'oauth_subject' => 'retired-subject', 'wiki_user_id' => 987654, 'wiki_username' => 'FalschesWikiKonto']);
    $service = app(AccountLinkService::class);
    $service->releaseDisconnectedLink($admin, $link, 'OAuth-Verknüpfung gehörte nachweislich zu einer anderen Person.');
    try {
        $service->activate($otherMember, new MaddraxikonIdentity(oauthSubject: 'retired-subject', wikiUserId: 987654, wikiUsername: 'FalschesWikiKonto'), 'correction-test', now());
        $this->fail('A tombstoned identity must never be reclaimable.');
    } catch (AccountLinkConflictException) {
        expect(true)->toBeTrue();
    }
    $newLink = $service->activate($member, new MaddraxikonIdentity(oauthSubject: 'fresh-proven-oauth-subject', wikiUserId: 987655, wikiUsername: 'RichtigesWikiKonto'), 'correction-test', now());
    expect($newLink->user_id)->toBe($member->id)
        ->and($newLink->oauth_subject)->toBe('fresh-proven-oauth-subject')
        ->and($newLink->wiki_user_id)->toBe(987655)
        ->and($newLink->wiki_username)->toBe('RichtigesWikiKonto');
    $this->assertDatabaseCount('maddraxikon_account_links', 1);
    $this->assertDatabaseCount('maddraxikon_account_link_corrections', 1);
});

test('admin modal requires reason and records the correction', function (): void {
    $admin = $this->actingAdmin();
    $admin->forceFill(['name' => 'Korrektur Admin'])->save();
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['name' => 'Betroffenes Mitglied'])->save();
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $member->id, 'wiki_username' => 'FalschImModal']);
    Livewire::test(MaddraxikonAdmin::class)->assertSee('Fehlzuordnung korrigieren')->call('openLinkCorrection', $link->id)->assertSet('showLinkCorrectionModal', true)->assertSet('correctingAccountLinkId', $link->id)->assertSee('keine neue Wiki-Identität manuell eingetragen')->set('linkCorrectionReason', '   ')->call('correctAccountLink')->assertHasErrors(['linkCorrectionReason' => 'required'])->set('linkCorrectionReason', str_repeat('x', 501))->call('correctAccountLink')->assertHasErrors(['linkCorrectionReason' => 'max'])->set('linkCorrectionReason', 'Falsches Konto nach Rücksprache mit dem Mitglied.')->call('correctAccountLink')->assertHasNoErrors()->assertSet('showLinkCorrectionModal', false)->assertSet('correctingAccountLinkId', null)->assertSee('Letzte Zuordnungskorrekturen')->assertSee('Falsches Konto nach Rücksprache mit dem Mitglied.')->assertDispatched('toast', type: 'success', title: 'Maddraxikon-Zuordnung zur Neuverknüpfung freigegeben');
    $this->assertDatabaseMissing('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseHas('maddraxikon_account_link_corrections', ['actor_user_id' => $admin->id, 'affected_user_id' => $member->id, 'old_wiki_username' => 'FalschImModal', 'reason' => 'Falsches Konto nach Rücksprache mit dem Mitglied.']);
});

test('non admin cannot open the correction component', function (): void {
    $this->actingMember();
    Livewire::test(MaddraxikonAdmin::class)->assertForbidden();
});
