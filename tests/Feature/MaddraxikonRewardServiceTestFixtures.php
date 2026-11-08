<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

trait MaddraxikonRewardServiceTestFixtures
{
    use RefreshDatabase;

    private int $nextExternalId = 10000;

    /**
     * @return array{User, MaddraxikonAccountLink}
     */
    private function linkedMember(): array
    {
        $user = User::factory()->create();
        Team::membersTeam()->users()->attach($user, ['role' => Role::Mitglied->value]);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $user->id, 'verified_at' => now()->subDays(3), 'first_verified_at' => now()->subDays(3)]);

        return [$user, $link];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Team::membersTeam()->users()->attach($admin, ['role' => Role::Admin->value]);

        return $admin;
    }

    private function contribution(MaddraxikonAccountLink $link, array $overrides = []): MaddraxikonContribution
    {
        $externalId = $this->nextExternalId++;
        $occurredAt = now()->subHours(30);

        return MaddraxikonContribution::query()->create(['wiki_key' => $link->wiki_key, 'rc_id' => $externalId, 'revision_id' => $externalId, 'parent_revision_id' => $externalId - 1, 'page_id' => $externalId, 'namespace_id' => 0, 'page_title' => 'Testartikel '.$externalId, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'account_link_id' => $link->id, 'user_id' => $link->user_id, 'type' => MaddraxikonContributionType::Edit, 'minor' => false, 'bot' => false, 'anonymous' => false, 'redirect' => false, 'user_hidden' => false, 'old_size' => 600, 'new_size' => 650, 'tags' => [], 'occurred_at' => $occurredAt, 'session_anchor_revision_id' => $externalId, 'status' => MaddraxikonContributionStatus::Pending, 'eligible_after' => $occurredAt->copy()->addDay(), ...$overrides]);
    }

    private function completeWatermark(): void
    {
        MaddraxikonSyncState::query()->updateOrCreate(['wiki_key' => config('maddraxikon.wiki_key')], ['watermark_at' => now()]);
    }

    /**
     * @param  list<array{int, int}>  $tiers
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPolicyWithTiers(array $tiers, array $overrides = []): MaddraxikonRewardPolicy
    {
        $policy = MaddraxikonRewardPolicy::factory()->create(['effective_from' => now()->subDays(4), ...$overrides]);
        foreach ($tiers as [$minimumAddedBytes, $points]) {
            MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => $minimumAddedBytes, 'points' => $points]);
        }
        $policy->update(['status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED, 'published_at' => now()->subDays(4)]);

        return $policy->fresh('tiers');
    }

    private function service(MaddraxikonApiClient $api): MaddraxikonRewardService
    {
        return new MaddraxikonRewardService($api);
    }

    private function apiWithValidRevisions($contributions): MaddraxikonApiClient
    {
        $byRevision = $contributions->keyBy('revision_id');
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->andReturnUsing(fn (array $revisionIds): array => collect($revisionIds)->mapWithKeys(function (int $revisionId) use ($byRevision): array {
            $contribution = $byRevision->get($revisionId);

            return [$revisionId => $this->revisionFor($contribution)];
        })->all());

        return $api;
    }

    private function apiForNewArticle(MaddraxikonContribution $article, array $revisionOverrides = [], array $pageOverrides = []): MaddraxikonApiClient
    {
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->once()->andReturn([$article->revision_id => $this->revisionFor($article, $revisionOverrides)]);
        $revisionFailsBeforePageCheck = ($revisionOverrides['tags'] ?? []) === ['mw-reverted'] || ($revisionOverrides['user_hidden'] ?? false) || ($revisionOverrides['suppressed'] ?? false) || ($revisionOverrides['sha1_hidden'] ?? false) || ($revisionOverrides['text_hidden'] ?? false);
        if (! $revisionFailsBeforePageCheck) {
            $api->shouldReceive('pageDetails')->once()->andReturn([$article->page_id => ['exists' => true, 'page_id' => $article->page_id, 'namespace_id' => 0, 'title' => $article->page_title, 'size' => 500, 'redirect' => false, ...$pageOverrides]]);
        }

        return $api;
    }

    private function revisionFor(MaddraxikonContribution $contribution, array $overrides = []): array
    {
        return ['exists' => true, 'revision_id' => $contribution->revision_id, 'page_id' => $contribution->page_id, 'namespace_id' => $contribution->namespace_id, 'user_id' => $contribution->wiki_user_id, 'user_hidden' => false, 'suppressed' => false, 'size' => $contribution->new_size, 'tags' => [], ...$overrides];
    }
}
