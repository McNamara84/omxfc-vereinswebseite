<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonStreakService;
use App\Services\Maddraxikon\MaddraxikonStreakTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

trait MaddraxikonStreakServiceTestFixtures
{
    use RefreshDatabase;

    protected User $member;

    protected MaddraxikonAccountLink $link;

    protected array $revisionOverrides = [];

    protected array $pageOverrides = [];

    protected function source(string $at = '2026-10-10T12:00:00+02:00', array $attributes = []): MaddraxikonContribution
    {
        $source = MaddraxikonContribution::factory()->create(['user_id' => $this->member->id, 'account_link_id' => $this->link->id, 'wiki_user_id' => $this->link->wiki_user_id, 'wiki_username' => $this->link->wiki_username, 'occurred_at' => CarbonImmutable::parse($at), ...$attributes]);
        app(MaddraxikonStreakTracker::class)->record($source, $this->link);

        return $source->fresh();
    }

    protected function evaluate(bool $recheck = false): int
    {
        return app(MaddraxikonStreakService::class)->run($recheck);
    }

    private function legacySource(string $localTime): MaddraxikonContribution
    {
        $source = MaddraxikonContribution::factory()->create(['user_id' => $this->member->id, 'account_link_id' => $this->link->id, 'wiki_user_id' => $this->link->wiki_user_id, 'wiki_username' => $this->link->wiki_username]);
        DB::table('maddraxikon_contributions')->where('id', $source->id)->update(['occurred_at' => $localTime, 'occurred_at_epoch' => null]);

        return $source->fresh();
    }

    private function backfillLegacyEpochs(): void
    {
        $migration = require database_path('migrations/2026_10_24_140000_backfill_maddraxikon_contribution_epochs.php');
        $migration->up();
    }

    protected function points(): int
    {
        return (int) UserPoint::query()->where('user_id', $this->member->id)->where('team_id', Team::membersTeam()->id)->sum('points');
    }
}
