<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacterRename;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgCharacterRenameFixtures;
use Tests\Support\RpgCheckFixtures;
use Tests\Support\RpgCombatFixtures;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCharacterRenameFixtures::class, RpgCombatFixtures::class, RpgInventoryFixtures::class, RpgCheckFixtures::class);

beforeEach(function (): void {
    $this->combatFixtures();
    $this->player->incrementTeamPoints(25);
});

test('old inventory preview cannot overwrite rename and fresh inventory preserves it', function (): void {
    $this->inventoryInitialize();
    $input = $this->inventoryInput('grant', ['amount' => 10]);
    $service = app(RpgInventoryService::class);
    $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
    $this->renameCharacter();
    expect(fn () => $service->book($this->leader, $this->character->id, $input))->toThrow(ValidationException::class);
    $this->inventoryBook('grant', ['amount' => 10]);
    expect($this->character->fresh()->payload['character']['character_name'])->toBe('Birka')
        ->and($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe(110);
});

test('old improvement preview is rejected and a fresh approved improvement keeps renamed character', function (): void {
    $this->credit();
    $input = $this->advancementInput();
    $service = app(RpgCharacterAdvancementService::class);
    $this->renameCharacter();
    expect(fn () => $service->submit($this->player, $this->character->id, $input))->toThrow(ValidationException::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->leader, $request->id, 'approved');
    expect($this->character->fresh()->character_name)->toBe('Birka')
        ->and($this->character->fresh()->payload['character']['character_name'])->toBe('Birka')
        ->and($this->character->experienceBalance())->toBe(54);
});

test('open invitations are explained before purchase and become stale after rename', function (): void {
    $combat = $this->combat(false);
    $snapshots = $combat->participants()->get()->map->getAttributes()->all();
    $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertOk()->assertSee('offene Kampfeinladungen');
    $this->renameCharacter();
    expect(fn () => app(CombatService::class)->command($this->opponent, $combat->id, 'accept', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($combat->fresh()->status)->toBe('challenged')
        ->and($combat->participants()->get()->map->getAttributes()->all())->toBe($snapshots);
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});

test('running combat continues with unchanged historic names and state after rename', function (): void {
    $combat = $this->activeCombat();
    $before = $combat->getAttributes();
    $participants = $combat->participants()->get()->map->getAttributes()->all();
    $this->renameCharacter();
    expect($combat->fresh()->getAttributes())->toBe($before)
        ->and($combat->participants()->get()->map->getAttributes()->all())->toBe($participants);
    $combat = app(CombatService::class)->command($this->player, $combat->id, 'surrender', (string) Str::uuid());
    expect($combat->status)->toBe('completed')->and($combat->winner_side)->toBe(2)
        ->and($this->character->fresh()->character_name)->toBe('Birka');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});

test('existing checks remain executable from their snapshot after rename', function (): void {
    $check = $this->createCheck();
    $participants = $check->participants()->get()->map->getAttributes()->all();
    $this->renameCharacter();
    expect($check->participants()->get()->map->getAttributes()->all())->toBe($participants);
    $this->dice([3, 4]);
    $check = $this->rollCheck($check);
    expect($check->status)->toBe('completed')->and(RpgCharacterRename::sole()->new_name)->toBe('Birka');
});
