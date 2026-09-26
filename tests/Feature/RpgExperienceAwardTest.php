<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\RpgExperienceEntry;
use App\Services\RpgExperienceAwardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgExperienceAwardTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
    }

    public function test_award_creates_one_credit_and_activity_even_when_retried(): void
    {
        $input = $this->awardInput();
        $service = app(RpgExperienceAwardService::class);
        $first = $service->award($this->leader, $input);
        $this->assertSame($first->id, $service->award($this->leader, $input)->id);
        $this->assertSame(60, $this->character->experienceBalance());
        $this->assertSame(1, RpgExperienceEntry::count());
        $this->assertSame(1, Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count());
        $this->assertSame($this->character->payload, $this->character->initial_payload);
    }

    public function test_zero_award_is_documented_without_public_activity(): void
    {
        $this->credit(0);
        $this->assertSame(1, RpgExperienceEntry::count());
        $this->assertSame(0, Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count());
    }

    public function test_changed_replay_is_rejected(): void
    {
        $input = $this->awardInput();
        app(RpgExperienceAwardService::class)->award($this->leader, $input);
        $input['title'] = 'Andere Angaben';
        $this->expectException(ValidationException::class);
        app(RpgExperienceAwardService::class)->award($this->leader, $input);
    }

    public function test_admin_alone_may_not_award_points(): void
    {
        $admin = $this->progressionMember(Role::Admin);
        $this->expectException(AuthorizationException::class);
        app(RpgExperienceAwardService::class)->award($admin, $this->awardInput());
    }

    public function test_former_member_cannot_receive_points(): void
    {
        $this->rpgTeam->users()->detach($this->player);
        try {
            $this->credit();
            $this->fail('Award should fail');
        } catch (ValidationException) {
            $this->assertSame(0, RpgExperienceEntry::count());
            $this->assertDatabaseCount('rpg_adventures', 0);
        }
    }

    public function test_credit_and_activity_roll_back_together(): void
    {
        Activity::creating(function (Activity $activity): void {
            if ($activity->action === Activity::ACTION_RPG_EXPERIENCE_AWARDED) {
                throw new \RuntimeException('Simulated activity failure');
            }
        });
        try {
            $this->credit();
            $this->fail('Expected simulated failure');
        } catch (\RuntimeException) {
            $this->assertSame(0, RpgExperienceEntry::count());
            $this->assertDatabaseCount('rpg_adventures', 0);
        } finally {
            Activity::flushEventListeners();
        }
    }
}
