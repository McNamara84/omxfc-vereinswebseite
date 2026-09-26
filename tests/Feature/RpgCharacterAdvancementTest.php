<?php

namespace Tests\Feature;

use App\Models\RpgExperienceEntry;
use App\Services\RpgCharacterAdvancementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgCharacterAdvancementTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
        $this->credit();
    }

    public function test_only_approval_changes_character_and_spends_points_once(): void
    {
        $service = app(RpgCharacterAdvancementService::class);
        $input = $this->advancementInput();
        $original = $this->character->payload;
        $request = $service->submit($this->player, $this->character->id, $input);
        $this->assertSame(60, $this->character->experienceBalance());
        $this->assertSame($original, $this->character->fresh()->payload);
        $service->decide($this->leader, $request->id, 'approved');
        $service->decide($this->leader, $request->id, 'approved');
        $this->assertSame($request->id, $service->submit($this->player, $this->character->id, $input)->id);
        $this->assertSame(54, $this->character->experienceBalance());
        $this->assertSame(1, $this->character->fresh()->revision);
        $this->assertSame(2, RpgExperienceEntry::count());
        $this->assertSame($original, $this->character->fresh()->initial_payload);
        $this->assertSame(3, collect($this->character->fresh()->payload['skills'])->firstWhere('name', 'Nahkampf')['value']);
    }

    public function test_rejection_and_withdrawal_do_not_spend_points(): void
    {
        $service = app(RpgCharacterAdvancementService::class);
        $first = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $service->decide($this->leader, $first->id, 'rejected', 'Im Abenteuer nicht begründet.');
        $second = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $service->decide($this->player, $second->id, 'withdrawn');
        $this->assertSame(60, $this->character->experienceBalance());
        $this->assertSame(0, $this->character->fresh()->revision);
        $this->expectException(ValidationException::class);
        $service->decide($this->leader, $second->id, 'approved');
    }

    public function test_only_one_pending_request_is_allowed(): void
    {
        $service = app(RpgCharacterAdvancementService::class);
        $service->submit($this->player, $this->character->id, $this->advancementInput());
        $this->expectException(ValidationException::class);
        $service->submit($this->player, $this->character->id, $this->advancementInput());
    }

    public function test_owner_cannot_approve(): void
    {
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $this->expectException(AuthorizationException::class);
        $service->decide($this->player, $request->id, 'approved');
    }

    public function test_foreign_character_cannot_be_improved(): void
    {
        $this->expectException(AuthorizationException::class);
        app(RpgCharacterAdvancementService::class)->submit($this->leader, $this->character->id, $this->advancementInput());
    }

    public function test_insufficient_points_block_entire_request(): void
    {
        $this->expectException(ValidationException::class);
        app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput([$this->operation(steps: 10)]));
    }

    public function test_changed_state_blocks_approval_without_partial_write(): void
    {
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $this->character->forceFill(['revision' => 1])->save();
        try {
            $service->decide($this->leader, $request->id, 'approved');
            $this->fail('Expected stale state rejection');
        } catch (ValidationException) {
            $this->assertSame('pending', $request->fresh()->status);
            $this->assertSame(60, $this->character->experienceBalance());
        }
    }
}
