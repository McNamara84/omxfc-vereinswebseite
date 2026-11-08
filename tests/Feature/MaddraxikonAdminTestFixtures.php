<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait MaddraxikonAdminTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function awardedRewardEvent(): MaddraxikonRewardEvent
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id]);
        $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'type' => MaddraxikonContributionType::Edit, 'status' => MaddraxikonContributionStatus::Awarded]);

        return MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $contribution->id, 'user_id' => $member->id, 'account_link_id' => $link->id, 'source_revision_id' => $contribution->revision_id, 'status' => MaddraxikonRewardEventStatus::Awarded, 'awarded_points' => 1, 'awarded_at' => now()]);
    }
}
