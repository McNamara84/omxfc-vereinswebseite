<?php

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

class MaddraxikonAccountLinkCorrectionTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.testing_minimal_layout', true);
    }

    public function test_migration_exposes_the_immutable_audit_fields(): void
    {
        $this->assertTrue(Schema::hasColumns(
            'maddraxikon_account_link_corrections',
            [
                'actor_user_id',
                'affected_user_id',
                'released_account_link_id',
                'wiki_key',
                'old_oauth_subject_hash',
                'old_wiki_user_id',
                'old_wiki_username',
                'reason',
                'corrected_at',
            ],
        ));
        $this->assertFalse(Schema::hasColumn(
            'maddraxikon_account_link_corrections',
            'updated_at',
        ));
    }

    public function test_tombstone_records_the_primary_hash_key_version(): void
    {
        config()->set('maddraxikon.identity_hmac_peppers', [
            'v2' => str_repeat('n', 32),
            'v1' => str_repeat('o', 32),
        ]);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'oauth_subject' => 'versioned-subject',
            'wiki_user_id' => 442_211,
        ]);

        $tombstone = MaddraxikonIdentityTombstone::retire($link);

        $this->assertSame('v2', $tombstone->hash_key_version);
        $this->assertSame(
            MaddraxikonIdentityHmacPeppers::fingerprint(
                $link->wiki_key,
                'v2',
                str_repeat('n', 32),
            ),
            $tombstone->hash_key_fingerprint,
        );
        $this->assertDatabaseHas('maddraxikon_identity_tombstones', [
            'id' => $tombstone->id,
            'hash_key_version' => 'v2',
            'hash_key_fingerprint' => $tombstone->hash_key_fingerprint,
            'oauth_subject_hash' => MaddraxikonIdentityTombstone::oauthSubjectHash(
                $link->wiki_key,
                $link->oauth_subject,
            ),
        ]);
    }

    public function test_reusing_a_stored_version_with_another_secret_fails_closed(): void
    {
        Mail::fake();
        $oldSecret = str_repeat('o', 32);
        config()->set('maddraxikon.identity_hmac_peppers', [
            'stable-version' => $oldSecret,
        ]);
        $historicalLink = MaddraxikonAccountLink::factory()
            ->disconnected()
            ->create([
                'oauth_subject' => 'historical-subject',
                'wiki_user_id' => 551_901,
            ]);
        $tombstone = MaddraxikonIdentityTombstone::retire($historicalLink);
        $historicalLink->delete();

        $this->assertSame(
            MaddraxikonIdentityHmacPeppers::fingerprint(
                'maddraxikon-de',
                'stable-version',
                $oldSecret,
            ),
            $tombstone->hash_key_fingerprint,
        );

        config()->set('maddraxikon.identity_hmac_peppers', [
            'stable-version' => str_repeat('n', 32),
        ]);

        try {
            app(AccountLinkService::class)->activate(
                $this->createUserWithRole(Role::Mitglied),
                new MaddraxikonIdentity(
                    oauthSubject: 'unrelated-new-subject',
                    wikiUserId: 551_902,
                    wikiUsername: 'NeuesKonto',
                ),
                'same-version-secret-change',
                now(),
            );
            $this->fail('A changed secret under an established version was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'Versionsnamen dürfen',
                $exception->getMessage(),
            );
            $this->assertStringContainsString(
                'stable-version',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Mail::assertNothingQueued();
    }

    public function test_tombstone_created_with_previous_pepper_blocks_identity_after_rotation(): void
    {
        Mail::fake();
        $oldPepper = str_repeat('o', 32);
        $newPepper = str_repeat('n', 32);
        config()->set('maddraxikon.identity_hmac_peppers', [
            'v1' => $oldPepper,
        ]);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'oauth_subject' => 'identity-before-rotation',
            'wiki_user_id' => 553_311,
            'wiki_username' => 'VorDerRotation',
        ]);
        $oldHash = MaddraxikonIdentityTombstone::retire($link)
            ->oauth_subject_hash;
        $link->delete();

        config()->set('maddraxikon.identity_hmac_peppers', [
            'v2' => $newPepper,
            'v1' => $oldPepper,
        ]);
        $this->assertNotSame(
            $oldHash,
            MaddraxikonIdentityTombstone::oauthSubjectHash(
                'maddraxikon-de',
                'identity-before-rotation',
            ),
        );

        try {
            app(AccountLinkService::class)->activate(
                $this->createUserWithRole(Role::Mitglied),
                new MaddraxikonIdentity(
                    oauthSubject: 'identity-before-rotation',
                    wikiUserId: 553_311,
                    wikiUsername: 'VorDerRotation',
                ),
                'rotation-test',
                now(),
            );
            $this->fail('A tombstone using a previous pepper must still block the identity.');
        } catch (AccountLinkConflictException) {
            $this->addToAssertionCount(1);
        }

        $this->assertContains(
            $oldHash,
            MaddraxikonIdentityTombstone::oauthSubjectHashes(
                'maddraxikon-de',
                'identity-before-rotation',
            ),
        );
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Mail::assertNothingQueued();
    }

    public function test_removing_historical_pepper_blocks_all_new_links(): void
    {
        Mail::fake();
        config()->set('maddraxikon.identity_hmac_peppers', [
            'v1' => str_repeat('o', 32),
        ]);
        $historicalLink = MaddraxikonAccountLink::factory()
            ->disconnected()
            ->create([
                'oauth_subject' => 'historical-pepper-subject',
                'wiki_user_id' => 597_311,
            ]);
        MaddraxikonIdentityTombstone::retire($historicalLink);
        $historicalLink->delete();

        config()->set('maddraxikon.identity_hmac_peppers', [
            'v2' => str_repeat('n', 32),
        ]);

        try {
            app(AccountLinkService::class)->activate(
                $this->createUserWithRole(Role::Mitglied),
                new MaddraxikonIdentity(
                    oauthSubject: 'entirely-new-subject',
                    wikiUserId: 597_312,
                    wikiUsername: 'VölligNeuesKonto',
                ),
                'missing-pepper-test',
                now(),
            );
            $this->fail('Linking must fail while a stored hash-key version is missing.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'HMAC-Schlüsselversionen',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('v1', $exception->getMessage());
        }

        $this->assertDatabaseHas('maddraxikon_identity_tombstones', [
            'hash_key_version' => 'v1',
        ]);
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Mail::assertNothingQueued();
    }

    public function test_legacy_app_key_tombstone_can_be_checked_during_migration(): void
    {
        Mail::fake();
        $legacyAppKey = 'base64:'.base64_encode(random_bytes(32));
        config()->set('app.key', $legacyAppKey);
        $wikiKey = 'maddraxikon-de';
        $oauthSubject = 'legacy-app-key-subject';
        $wikiUserId = 618_244;
        $legacySubjectHash = hash_hmac(
            'sha256',
            implode("\0", [$wikiKey, 'oauth-subject', $oauthSubject]),
            $legacyAppKey,
        );
        $legacyWikiUserIdHash = hash_hmac(
            'sha256',
            implode("\0", [$wikiKey, 'wiki-user-id', (string) $wikiUserId]),
            $legacyAppKey,
        );
        MaddraxikonIdentityTombstone::query()->create([
            'wiki_key' => $wikiKey,
            'hash_key_version' => 'legacy-app-key',
            'hash_key_fingerprint' => MaddraxikonIdentityHmacPeppers::fingerprint(
                $wikiKey,
                'legacy-app-key',
                $legacyAppKey,
            ),
            'oauth_subject_hash' => $legacySubjectHash,
            'wiki_user_id_hash' => $legacyWikiUserIdHash,
            'retired_at' => now(),
        ]);

        config()->set('maddraxikon.identity_hmac_peppers', [
            'v1' => str_repeat('n', 32),
            'legacy-app-key' => 'raw:'.$legacyAppKey,
        ]);

        $this->assertNotSame(
            $legacySubjectHash,
            MaddraxikonIdentityTombstone::oauthSubjectHash(
                $wikiKey,
                $oauthSubject,
            ),
        );
        $this->assertContains(
            $legacySubjectHash,
            MaddraxikonIdentityTombstone::oauthSubjectHashes(
                $wikiKey,
                $oauthSubject,
            ),
        );

        try {
            app(AccountLinkService::class)->activate(
                $this->createUserWithRole(Role::Mitglied),
                new MaddraxikonIdentity(
                    oauthSubject: $oauthSubject,
                    wikiUserId: $wikiUserId,
                    wikiUsername: 'HistorischeIdentität',
                ),
                'legacy-app-key-test',
                now(),
            );
            $this->fail('A tombstone hashed with the former APP_KEY must still block.');
        } catch (AccountLinkConflictException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('maddraxikon_identity_tombstones', [
            'oauth_subject_hash' => $legacySubjectHash,
            'hash_key_version' => 'legacy-app-key',
        ]);
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Mail::assertNothingQueued();
    }

    public function test_oauth_link_conflict_is_logged_without_raw_identity_data(): void
    {
        Mail::fake();
        $owner = $this->createUserWithRole(Role::Mitglied);
        $claimant = $this->createUserWithRole(Role::Mitglied);
        MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $owner->id,
            'oauth_subject' => 'raw-sensitive-oauth-subject',
            'wiki_user_id' => 664_422,
            'wiki_username' => 'RawSensitiveUsername',
        ]);
        Log::spy();

        try {
            app(AccountLinkService::class)->activate(
                $claimant,
                new MaddraxikonIdentity(
                    oauthSubject: 'raw-sensitive-oauth-subject',
                    wikiUserId: 664_422,
                    wikiUsername: 'RawSensitiveUsername',
                ),
                'logging-test',
                now(),
            );
            $this->fail('A claimed identity must result in a conflict.');
        } catch (AccountLinkConflictException) {
            $this->addToAssertionCount(1);
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($claimant): bool {
                $this->assertSame(
                    'Maddraxikon OAuth account-link conflict.',
                    $message,
                );
                $this->assertSame(
                    [
                        'event',
                        'reason',
                        'user_id',
                        'wiki_key',
                        'oauth_subject_fingerprint',
                        'wiki_user_id_fingerprint',
                    ],
                    array_keys($context),
                );
                $this->assertSame('identity_already_claimed', $context['reason']);
                $this->assertSame($claimant->id, $context['user_id']);
                $this->assertNotContains(
                    'raw-sensitive-oauth-subject',
                    array_values($context),
                    true,
                );
                $this->assertNotContains(
                    'RawSensitiveUsername',
                    array_values($context),
                    true,
                );
                $this->assertNotContains(664_422, array_values($context), true);

                return true;
            });
    }

    public function test_identity_hashing_fails_closed_without_a_dedicated_pepper(): void
    {
        config()->set('maddraxikon.identity_hmac_peppers', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MADDRAXIKON_IDENTITY_HMAC_PEPPERS');

        MaddraxikonIdentityTombstone::oauthSubjectHash(
            'maddraxikon-de',
            'must-not-fall-back-to-app-key',
        );
    }

    public function test_identity_hash_is_stable_when_laravel_app_key_changes(): void
    {
        config()->set('maddraxikon.identity_hmac_peppers', [
            'v1' => str_repeat('p', 32),
        ]);
        config()->set('app.key', 'base64:first-unrelated-laravel-app-key');
        $before = MaddraxikonIdentityTombstone::oauthSubjectHash(
            'maddraxikon-de',
            'stable-subject',
        );

        config()->set('app.key', 'base64:second-unrelated-laravel-app-key');
        $after = MaddraxikonIdentityTombstone::oauthSubjectHash(
            'maddraxikon-de',
            'stable-subject',
        );

        $this->assertSame($before, $after);
        $this->assertSame(
            hash_hmac(
                'sha256',
                implode("\0", ['maddraxikon-de', 'oauth-subject', 'stable-subject']),
                str_repeat('p', 32),
            ),
            $after,
        );
    }

    public function test_base64_pepper_is_decoded_before_hashing(): void
    {
        $pepper = random_bytes(32);
        config()->set('maddraxikon.identity_hmac_peppers', [
            'v1' => 'base64:'.base64_encode($pepper),
        ]);

        $hash = MaddraxikonIdentityTombstone::wikiUserIdHash(
            'maddraxikon-de',
            775_533,
        );

        $this->assertSame(
            hash_hmac(
                'sha256',
                implode("\0", [
                    'maddraxikon-de',
                    'wiki-user-id',
                    '775533',
                ]),
                $pepper,
            ),
            $hash,
        );
    }

    public function test_legacy_app_key_cannot_be_the_primary_pepper(): void
    {
        config()->set('maddraxikon.identity_hmac_peppers', [
            'legacy-app-key' => 'raw:base64:'.base64_encode(random_bytes(32)),
            'v1' => str_repeat('n', 32),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Legacy-APP_KEY darf nicht der primäre Identitäts-Pepper sein',
        );

        MaddraxikonIdentityTombstone::oauthSubjectHash(
            'maddraxikon-de',
            'legacy-must-not-write-new-hashes',
        );
    }

    public function test_admin_correction_is_audited_and_preserves_source_snapshots(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
            'oauth_subject' => 'wrong-opaque-oauth-subject',
            'wiki_user_id' => 71_337,
            'wiki_username' => 'FalschZugeordnet',
        ]);
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
        ]);
        $rewardEvent = MaddraxikonRewardEvent::factory()->create([
            'account_link_id' => $link->id,
            'source_contribution_id' => $contribution->id,
            'user_id' => $member->id,
            'source_revision_id' => $contribution->revision_id,
            'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward,
        ]);

        $correction = app(AccountLinkService::class)
            ->releaseDisconnectedLink(
                $admin,
                $link,
                'Mitglied und Wiki-Konto waren dauerhaft falsch zugeordnet.',
            );

        $this->assertDatabaseMissing('maddraxikon_account_links', [
            'id' => $link->id,
        ]);
        $this->assertDatabaseHas('maddraxikon_identity_tombstones', [
            'wiki_key' => $link->wiki_key,
            'oauth_subject_hash' => MaddraxikonIdentityTombstone::oauthSubjectHash(
                $link->wiki_key,
                $link->oauth_subject,
            ),
            'wiki_user_id_hash' => MaddraxikonIdentityTombstone::wikiUserIdHash(
                $link->wiki_key,
                $link->wiki_user_id,
            ),
        ]);
        $this->assertSame($admin->id, $correction->actor_user_id);
        $this->assertSame($member->id, $correction->affected_user_id);
        $this->assertSame($link->id, $correction->released_account_link_id);
        $this->assertSame($link->wiki_key, $correction->wiki_key);
        $this->assertSame($link->wiki_user_id, $correction->old_wiki_user_id);
        $this->assertSame(
            $link->wiki_username,
            $correction->old_wiki_username,
        );
        $this->assertSame(
            'Mitglied und Wiki-Konto waren dauerhaft falsch zugeordnet.',
            $correction->reason,
        );
        $this->assertSame(
            MaddraxikonIdentityTombstone::oauthSubjectHash(
                $link->wiki_key,
                $link->oauth_subject,
            ),
            $correction->old_oauth_subject_hash,
        );
        $this->assertNotSame(
            $link->oauth_subject,
            $correction->old_oauth_subject_hash,
        );
        $this->assertTrue($correction->actor->is($admin));
        $this->assertTrue($correction->affectedUser->is($member));

        $contribution->refresh();
        $rewardEvent->refresh();

        $this->assertNull($contribution->account_link_id);
        $this->assertSame('FalschZugeordnet', $contribution->wiki_username);
        $this->assertSame(71_337, $contribution->wiki_user_id);
        $this->assertNull($rewardEvent->account_link_id);
        $this->assertSame(
            $contribution->id,
            $rewardEvent->source_contribution_id,
        );
    }

    public function test_only_admins_may_release_a_disconnected_link(): void
    {
        $actor = $this->createUserWithRole(Role::Mitglied);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
        ]);

        try {
            app(AccountLinkService::class)->releaseDisconnectedLink(
                $actor,
                $link,
                'Nicht autorisierter Versuch',
            );
            $this->fail('A non-admin correction must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'Nur Administratoren',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'id' => $link->id,
        ]);
        $this->assertDatabaseCount(
            'maddraxikon_account_link_corrections',
            0,
        );
        $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
    }

    public function test_active_link_cannot_be_released(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
        ]);

        try {
            app(AccountLinkService::class)->releaseDisconnectedLink(
                $admin,
                $link,
                'Aktive Verbindung darf nicht gelöscht werden',
            );
            $this->fail('An active link must not be released.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'bereits getrennte',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'id' => $link->id,
        ]);
        $this->assertDatabaseCount(
            'maddraxikon_account_link_corrections',
            0,
        );
        $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
    }

    public function test_service_requires_a_reason_before_mutating_data(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(AccountLinkService::class)->releaseDisconnectedLink(
                $admin,
                $link,
                '   ',
            );
        } finally {
            $this->assertDatabaseHas('maddraxikon_account_links', [
                'id' => $link->id,
            ]);
            $this->assertDatabaseCount(
                'maddraxikon_account_link_corrections',
                0,
            );
            $this->assertDatabaseCount(
                'maddraxikon_identity_tombstones',
                0,
            );
        }
    }

    public function test_audit_failure_rolls_back_tombstone_and_link_deletion(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
        ]);
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
        ]);

        MaddraxikonAccountLinkCorrection::creating(
            static fn () => throw new RuntimeException('Audit write failed'),
        );

        try {
            app(AccountLinkService::class)->releaseDisconnectedLink(
                $admin,
                $link,
                'Dieser Versuch wird vollständig zurückgerollt.',
            );
            $this->fail('The injected audit failure must bubble up.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit write failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'id' => $link->id,
        ]);
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'id' => $contribution->id,
            'account_link_id' => $link->id,
        ]);
        $this->assertDatabaseCount(
            'maddraxikon_account_link_corrections',
            0,
        );
        $this->assertDatabaseCount('maddraxikon_identity_tombstones', 0);
    }

    public function test_correction_audit_cannot_be_changed_or_deleted(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
        ]);
        $correction = app(AccountLinkService::class)
            ->releaseDisconnectedLink(
                $admin,
                $link,
                'Unveränderlicher Originalgrund',
            );

        try {
            $correction->forceFill(['reason' => 'Manipulierter Grund'])->save();
            $this->fail('Audit updates must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'unveränderlich',
                $exception->getMessage(),
            );
        }

        $correction->refresh();
        $this->assertSame(
            'Unveränderlicher Originalgrund',
            $correction->reason,
        );

        try {
            $correction->delete();
            $this->fail('Audit deletion must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'nicht gelöscht',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas(
            'maddraxikon_account_link_corrections',
            [
                'id' => $correction->id,
                'reason' => 'Unveränderlicher Originalgrund',
            ],
        );
    }

    public function test_old_identity_stays_blocked_but_member_can_relink_a_new_oauth_identity(): void
    {
        Mail::fake();
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        $otherMember = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
            'oauth_subject' => 'retired-subject',
            'wiki_user_id' => 987_654,
            'wiki_username' => 'FalschesWikiKonto',
        ]);
        $service = app(AccountLinkService::class);

        $service->releaseDisconnectedLink(
            $admin,
            $link,
            'OAuth-Verknüpfung gehörte nachweislich zu einer anderen Person.',
        );

        try {
            $service->activate(
                $otherMember,
                new MaddraxikonIdentity(
                    oauthSubject: 'retired-subject',
                    wikiUserId: 987_654,
                    wikiUsername: 'FalschesWikiKonto',
                ),
                'correction-test',
                now(),
            );
            $this->fail('A tombstoned identity must never be reclaimable.');
        } catch (AccountLinkConflictException) {
            $this->assertTrue(true);
        }

        $newLink = $service->activate(
            $member,
            new MaddraxikonIdentity(
                oauthSubject: 'fresh-proven-oauth-subject',
                wikiUserId: 987_655,
                wikiUsername: 'RichtigesWikiKonto',
            ),
            'correction-test',
            now(),
        );

        $this->assertSame($member->id, $newLink->user_id);
        $this->assertSame(
            'fresh-proven-oauth-subject',
            $newLink->oauth_subject,
        );
        $this->assertSame(987_655, $newLink->wiki_user_id);
        $this->assertSame('RichtigesWikiKonto', $newLink->wiki_username);
        $this->assertDatabaseCount('maddraxikon_account_links', 1);
        $this->assertDatabaseCount(
            'maddraxikon_account_link_corrections',
            1,
        );
    }

    public function test_admin_modal_requires_reason_and_records_the_correction(): void
    {
        $admin = $this->actingAdmin();
        $admin->forceFill(['name' => 'Korrektur Admin'])->save();
        $member = $this->createUserWithRole(Role::Mitglied);
        $member->forceFill(['name' => 'Betroffenes Mitglied'])->save();
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $member->id,
            'wiki_username' => 'FalschImModal',
        ]);

        Livewire::test(MaddraxikonAdmin::class)
            ->assertSee('Fehlzuordnung korrigieren')
            ->call('openLinkCorrection', $link->id)
            ->assertSet('showLinkCorrectionModal', true)
            ->assertSet('correctingAccountLinkId', $link->id)
            ->assertSee('keine neue Wiki-Identität manuell eingetragen')
            ->set('linkCorrectionReason', '   ')
            ->call('correctAccountLink')
            ->assertHasErrors(['linkCorrectionReason' => 'required'])
            ->set(
                'linkCorrectionReason',
                str_repeat('x', 501),
            )
            ->call('correctAccountLink')
            ->assertHasErrors(['linkCorrectionReason' => 'max'])
            ->set(
                'linkCorrectionReason',
                'Falsches Konto nach Rücksprache mit dem Mitglied.',
            )
            ->call('correctAccountLink')
            ->assertHasNoErrors()
            ->assertSet('showLinkCorrectionModal', false)
            ->assertSet('correctingAccountLinkId', null)
            ->assertSee('Letzte Zuordnungskorrekturen')
            ->assertSee('Falsches Konto nach Rücksprache mit dem Mitglied.')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: 'Maddraxikon-Zuordnung zur Neuverknüpfung freigegeben',
            );

        $this->assertDatabaseMissing('maddraxikon_account_links', [
            'id' => $link->id,
        ]);
        $this->assertDatabaseHas(
            'maddraxikon_account_link_corrections',
            [
                'actor_user_id' => $admin->id,
                'affected_user_id' => $member->id,
                'old_wiki_username' => 'FalschImModal',
                'reason' => 'Falsches Konto nach Rücksprache mit dem Mitglied.',
            ],
        );
    }

    public function test_non_admin_cannot_open_the_correction_component(): void
    {
        $this->actingMember();

        Livewire::test(MaddraxikonAdmin::class)->assertForbidden();
    }
}
