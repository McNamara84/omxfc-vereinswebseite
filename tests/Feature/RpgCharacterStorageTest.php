<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgCharacterSlotService;
use App\Services\RpgInventoryAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCharacterStorageTestFixtures::class);

test('ag rollenspiel member can store first character for free', function (): void {
    Storage::fake('private');
    $member = $this->actingAgMember();
    $response = $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['portrait' => UploadedFile::fake()->image('avatar.png', 10, 10)]));
    $response->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success');
    $character = RpgCharacter::query()->firstOrFail();
    expect($character->user_id)->toBe($member->id)
        ->and($character->character_name)->toBe('Foo Bar')
        ->and($character->payload['character']['race'])->toBe('Barbar')
        ->and($character->portrait_path)->not->toBeNull();
    Storage::disk('private')->assertExists($character->portrait_path);
    expect(RewardPurchase::query()->count())->toBe(0);
});

test('saving preserves the selected unarmed attack and its pdf values', function (): void {
    $this->actingAgMember();
    $input = $this->validCharacterPayload();
    $input['equipment_items'][] = ['id' => 'faustschlag-tritt', 'quantity' => 1];
    $this->post(route('rpg.characters.store'), $input)->assertSessionHasNoErrors()->assertRedirect(route('rpg.characters.index'));
    $character = RpgCharacter::firstOrFail();
    $unarmed = collect($character->initial_payload['equipment']['items'])->firstWhere('id', 'faustschlag-tritt');
    expect($unarmed)->not->toBeNull()
        ->and($character->payload['equipment']['items'])->toContain($unarmed)
        ->and(app(RpgInventoryAdapter::class)->state($character->payload)['stocks'])->not->toHaveKey('item:faustschlag-tritt');
    $combat = app(RpgCharacterCombatCalculator::class)->calculate($character->payload);
    $weapon = collect($combat['weapons'])->firstWhere('id', 'faustschlag-tritt');
    expect($weapon)->not->toBeNull()
        ->and($weapon['attack'])->toBe(3)
        ->and($weapon['damage_modifier'])->toBe(1);
    $sheet = app(RpgCharacterSheetPresenter::class)->present($character->payload, $combat);
    $html = view('rpg.char-sheet', ['sheet' => $sheet, 'combat' => $combat])->render();
    expect($html)->toContain('Faustschlag / Tritt')
        ->toContain('1W6+1+VM');
});

test('ag rollenspiel member can store techno with full fp budget', function (): void {
    $member = $this->actingAgMember();
    $response = $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Techno Vollbudget', 'race' => 'Techno', 'culture' => 'Bunkermensch', 'attributes' => ['st' => -1, 'ro' => -1, 'in' => 1, 'ge' => 1, 'wa' => 1], 'skills' => [['name' => 'Bildung', 'value' => 3], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Pilot', 'value' => 3], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2], ['name' => 'Athletik', 'value' => 4], ['name' => 'Diebeskunst', 'value' => 4], ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Handeln', 'value' => 4], ['name' => 'Reiten', 'value' => 4]], 'techno_skill_points' => ['Fahren' => 4, 'Feuerwaffen' => 2, 'Heiler' => 0, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2], 'bunkermensch_bonus_skill' => 'Pilot', 'advantages' => ['Zaeh', 'High-Tech-Ausruestung'], 'disadvantages' => ['Toedliche Immunschwaeche']]));
    $response->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success');
    $character = RpgCharacter::query()->firstOrFail();
    expect($character->user_id)->toBe($member->id)
        ->and($character->character_name)->toBe('Techno Vollbudget')
        ->and($character->payload['character']['race'])->toBe('Techno');
});

test('store rejects unspent level dependent ap and fp budgets', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['figurenstaerke' => 4]))->assertSessionHasErrors('attributes');
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['figurenstaerke' => 4, 'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1]]))->assertSessionHasErrors('skills');
    expect(RpgCharacter::query()->count())->toBe(0);
});

test('non editor member cannot use character storage routes', function (): void {
    $owner = $this->actingAgMember();
    $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);
    $member = $this->createMember();
    $this->actingAs($member)->get(route('rpg.characters.index'))->assertForbidden();
    $this->actingAs($member)->post(route('rpg.characters.store'), $this->validCharacterPayload())->assertForbidden();
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->get(route('rpg.characters.pdf', $character))->assertForbidden();
    $this->actingAs($member)->delete(route('rpg.characters.destroy', $character))->assertForbidden();
});

test('character list only shows current users characters', function (): void {
    $member = $this->actingAgMember();
    $otherMember = $this->addAgRollenspielMembership($this->createMember());
    RpgCharacter::factory()->create(['user_id' => $member->id, 'character_name' => 'Eigener Held']);
    RpgCharacter::factory()->create(['user_id' => $otherMember->id, 'character_name' => 'Fremder Held']);
    $this->get(route('rpg.characters.index'))->assertOk()->assertSee('Eigener Held')->assertDontSee('Fremder Held')->assertSee('target="_blank"', false)->assertSee('rel="noopener noreferrer"', false);
});

test('empty character name fallback is persisted to payload', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => '   ']))->assertRedirect(route('rpg.characters.index'));
    $character = RpgCharacter::query()->firstOrFail();
    expect($character->character_name)->toBe('Charakter')
        ->and($character->payload['character']['character_name'])->toBe('Charakter');
});

test('zero character name is persisted without fallback', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => '0']))->assertRedirect(route('rpg.characters.index'));
    $character = RpgCharacter::query()->firstOrFail();
    expect($character->character_name)->toBe('0')
        ->and($character->payload['character']['character_name'])->toBe('0');
});

test('character name above database column length is rejected', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => str_repeat('A', 256)]))->assertSessionHasErrors('character_name');
    expect(RpgCharacter::query()->count())->toBe(0);
});

test('second character without free slot is rejected', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Erster Held']))->assertRedirect(route('rpg.characters.index'));
    config(['rewards.rpg_character_slot_cost_baxx' => 7]);
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Zweiter Held']))->assertSessionHasErrors('slot');
    $slotError = session('errors')->first('slot');
    expect($slotError)->toContain('7 Baxx')->not->toContain('5 Baxx')
        ->and(RpgCharacter::query()->count())->toBe(1);
});

test('rejected store without free slot does not write portrait', function (): void {
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Erster Held']))->assertRedirect(route('rpg.characters.index'));
    $image = UploadedFile::fake()->image('avatar.png', 1, 1);
    $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());
    Storage::shouldReceive('disk')->never();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Zweiter Held', 'portrait_data_url' => $dataUrl]))->assertSessionHasErrors('slot');
    expect(RpgCharacter::query()->count())->toBe(1);
});

test('slot purchase costs five baxx and is repeatable', function (): void {
    $member = $this->actingAgMember(15);
    $this->post(route('rpg.characters.slots.purchase'))->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success');
    $this->post(route('rpg.characters.slots.purchase'))->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success');
    expect(RewardPurchase::query()->count())->toBe(2)
        ->and((int) RewardPurchase::query()->sum('cost_baxx'))->toBe(10)
        ->and(app(RpgCharacterSlotService::class)->totalSlots($member))->toBe(3)
        ->and($member->fresh()->getAvailableBaxx())->toBe(5);
});

test('slot purchase with insufficient baxx is rejected', function (): void {
    $this->actingAgMember(4);
    $this->post(route('rpg.characters.slots.purchase'))->assertRedirect(route('rpg.characters.index'))->assertSessionHasErrors('slot');
    expect(RewardPurchase::query()->count())->toBe(0);
});

test('store can confirm slot purchase when storage is full', function (): void {
    $member = $this->actingAgMember(5);
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Erster Held']))->assertRedirect(route('rpg.characters.index'));
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Zweiter Held', 'purchase_slot_if_needed' => '1']))->assertRedirect(route('rpg.characters.index'));
    expect(RpgCharacter::query()->count())->toBe(2)
        ->and(RewardPurchase::query()->count())->toBe(1)
        ->and($member->fresh()->getAvailableBaxx())->toBe(0);
});

test('failed store redirect keeps editor input for recovery', function (): void {
    $this->actingAgMember();
    $response = $this->from(route('rpg.char-editor'))->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'Kept Store Character', 'portrait_data_url' => 'data:image/png;base64,'.base64_encode('not an image')]));
    $response->assertRedirect(route('rpg.char-editor'))->assertSessionHasErrors('portrait_data_url')->assertSessionHasInput('character_name', 'Kept Store Character')->assertSessionHasInput('race', 'Barbar')->assertSessionHasInput('clothing', 'kleidung-einfach');
    expect(RpgCharacter::query()->count())->toBe(0);
});

test('failed portrait validation does not expose old portrait payload to editor', function (): void {
    $this->actingAgMember();
    foreach (['portrait_data_url', 'portrait'] as $errorField) {
        $payloadMarker = 'OVERSIZEDPORTRAITPAYLOAD'.strtoupper($errorField);
        $overrides = ['character_name' => 'Kept Portrait Character', 'portrait_data_url' => 'data:image/png;base64,'.$payloadMarker];
        if ($errorField === 'portrait') {
            $overrides['portrait'] = UploadedFile::fake()->create('avatar.txt', 1, 'text/plain');
        }
        $response = $this->followingRedirects()->from(route('rpg.char-editor'))->post(route('rpg.characters.store'), $this->validCharacterPayload($overrides));
        $response->assertOk()->assertSee('Kept Portrait Character')->assertDontSee($payloadMarker);
    }
});

test('failed portrait storage rejects character without persisting path', function (): void {
    $this->actingAgMember();
    $image = UploadedFile::fake()->image('avatar.png', 1, 1);
    $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());
    $disk = \Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->with(\Mockery::on(static fn (string $path): bool => str_starts_with($path, 'rpg-characters/') && str_ends_with($path, '.png')), \Mockery::type('string'))->andReturn(false);
    Storage::shouldReceive('disk')->with('private')->once()->andReturn($disk);
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['portrait_data_url' => $dataUrl]))->assertSessionHasErrors('portrait_data_url');
    expect(RpgCharacter::query()->count())->toBe(0);
});

test('failed uploaded portrait storage attaches error to upload field', function (): void {
    $this->actingAgMember();
    $disk = \Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->with(\Mockery::on(static fn (string $path): bool => str_starts_with($path, 'rpg-characters/') && str_ends_with($path, '.png')), \Mockery::type('string'))->andReturn(false);
    Storage::shouldReceive('disk')->with('private')->once()->andReturn($disk);
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['portrait' => UploadedFile::fake()->image('avatar.png', 1, 1)]))->assertSessionHasErrors('portrait');
    expect(session('errors')->has('portrait_data_url'))->toBeFalse()
        ->and(RpgCharacter::query()->count())->toBe(0);
});

test('deleting character frees slot and removes portrait file', function (): void {
    Storage::fake('private');
    $this->actingAgMember();
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['portrait' => UploadedFile::fake()->image('avatar.png', 10, 10)]))->assertRedirect(route('rpg.characters.index'));
    $character = RpgCharacter::query()->firstOrFail();
    $path = $character->portrait_path;
    Storage::disk('private')->assertExists($path);
    $this->delete(route('rpg.characters.destroy', $character))->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success');
    expect(RpgCharacter::query()->count())->toBe(0);
    Storage::disk('private')->assertMissing($path);
});

test('saved character can be rendered as pdf with stored portrait', function (): void {
    Storage::fake('private');
    $this->actingAgMember();
    $description = str_repeat('Eine ausführlich beschriebene Figur. ', 400).'ENDE';
    $this->post(route('rpg.characters.store'), $this->validCharacterPayload(['character_name' => 'PDF Held', 'description' => $description, 'portrait' => UploadedFile::fake()->image('avatar.png', 10, 10)]))->assertRedirect(route('rpg.characters.index'));
    $character = RpgCharacter::query()->firstOrFail();
    $storedPayload = $character->payload;
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn (array $data): bool => $data['character']['character_name'] === 'PDF Held' && str_starts_with($data['portrait'] ?? '', 'data:image/png;base64,') && $data['character']['description'] === $description && $data['sheet']['description'] === $description && $data['sheet']['layout']['description']['truncated'] && $data['sheet']['layout']['description']['font_size'] === 6.5))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $this->get(route('rpg.characters.pdf', $character))->assertOk();
    expect($character->fresh()->payload)->toBe($storedPayload);
});

test('saved character pdf is owner scoped', function (): void {
    $owner = $this->actingAgMember();
    $otherMember = $this->addAgRollenspielMembership($this->createMember());
    $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);
    Pdf::shouldReceive('view')->never();
    $this->actingAs($otherMember)->get(route('rpg.characters.pdf', $character))->assertForbidden();
});

test('saved character delete is owner scoped', function (): void {
    $owner = $this->actingAgMember();
    $otherMember = $this->addAgRollenspielMembership($this->createMember());
    $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($otherMember)->delete(route('rpg.characters.destroy', $character))->assertForbidden();
    $this->assertModelExists($character);
});
