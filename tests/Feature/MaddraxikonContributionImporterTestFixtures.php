<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonContributionImporterTestFixtures
{
    use RefreshDatabase;

    private function makeEligible(MaddraxikonAccountLink $link): Team
    {
        $membersTeam = Team::query()->where('name', 'Mitglieder')->first();
        if (! $membersTeam) {
            $membersTeam = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
            Team::clearMembersTeamCache();
        }
        $membersTeam->users()->syncWithoutDetaching([$link->user_id => ['role' => Role::Mitglied->value]]);

        return $membersTeam;
    }

    /**
     * @return array<string, mixed>
     */
    private function userContribution(int $revisionId, int $wikiUserId, string $timestamp, bool $isNew = false, int $pageId = 500): array
    {
        return ['userid' => $wikiUserId, 'user' => 'Neuer Name', 'pageid' => $pageId, 'revid' => $revisionId, 'parentid' => $isNew ? 0 : $revisionId - 1, 'ns' => 0, 'title' => $isNew ? 'Neuer Artikel' : 'Bestehender Artikel', 'timestamp' => $timestamp, 'size' => 650, 'sizediff' => $isNew ? 650 : 50, 'tags' => [], 'new' => $isNew, 'minor' => false, 'bot' => false, 'redirect' => false];
    }

    /**
     * @return array<string, mixed>
     */
    private function change(int $rcId, int $revisionId, int $wikiUserId, string $wikiUsername, string $timestamp, string $type = 'edit', int $pageId = 500, int $namespaceId = 0, int $oldRevisionId = 100, int $oldSize = 600, int $newSize = 650): array
    {
        return ['type' => $type, 'ns' => $namespaceId, 'title' => $type === 'new' ? 'Neuer Artikel' : 'Bestehender Artikel', 'pageid' => $pageId, 'revid' => $revisionId, 'old_revid' => $oldRevisionId, 'rcid' => $rcId, 'user' => $wikiUsername, 'userid' => $wikiUserId, 'oldlen' => $oldSize, 'newlen' => $newSize, 'timestamp' => $timestamp, 'minor' => false, 'bot' => false, 'anon' => false, 'redirect' => false, 'userhidden' => false, 'tags' => []];
    }
}
