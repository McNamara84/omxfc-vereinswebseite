<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fanfiction;
use App\Models\Reward;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

trait FanfictionCommentControllerTestFixtures
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $member;

    private User $otherMember;

    private Fanfiction $fanfiction;

    // ── Hilfsmethoden ─────────────────────────────────────────
    private function createRewardForFanfiction(Fanfiction $fanfiction, int $costBaxx = 5): Reward
    {
        $reward = Reward::create(['title' => $fanfiction->title, 'slug' => 'fanfiction-'.Str::slug($fanfiction->title).'-'.$fanfiction->id, 'description' => Str::limit($fanfiction->content ?? '', 200), 'category' => 'Fanfiction', 'cost_baxx' => $costBaxx, 'is_active' => true, 'sort_order' => 0]);
        $fanfiction->update(['reward_id' => $reward->id]);

        return $reward;
    }
}
