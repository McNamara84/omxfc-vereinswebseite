<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgInventoryFixtures::class);
dataset('RpgInventoryControllerTest.ownerNames', static fn (): array => ['alias takes priority' => [['alias' => ' Nachtfalke ', 'name' => 'Anzeigename', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Nachtfalke'], 'name without alias' => [['alias' => ' ', 'name' => ' Anzeigename ', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Anzeigename'], 'first and last name fallback' => [['alias' => null, 'name' => '', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Mara Wanderer'], 'first name alone' => [['alias' => null, 'name' => '', 'vorname' => 'Mara', 'nachname' => ''], 'Mara']]);
dataset('RpgInventoryControllerTest.ownerSearches', static fn (): array => ['character name' => ['Ruinenjäger', true], 'alias' => ['Nachtfalke', true], 'name' => ['Anzeigename', true], 'first name' => ['Mara', true], 'last name' => ['Wanderer', true], 'partial alias and surrounding whitespace' => [' Nacht ', true], 'no match' => ['GibtEsGarNicht', false]]);
beforeEach(function (): void {
    // The MariaDB job runs these controller assertions without an asset build.
    $this->withoutVite();
    $this->progressionFixtures();
    $this->withoutExceptionHandling();
});
test('inventory index displays the actual owner name', function (array $attributes, string $expected): void {
    $this->player->update($attributes);
    $this->actingAs($this->leader)->get(route('rpg.inventory.index'))->assertOk()->assertSeeText($expected)->assertViewHas('characters', fn ($characters) => $characters->count() === 1 && $characters->first()->user->nicknameOrName() === $expected);
})->with('RpgInventoryControllerTest.ownerNames');
test('inventory counterparties display the actual owner name', function (array $attributes, string $expected): void {
    $this->player->update($attributes);
    $peer = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'character_name' => 'Handelsfigur', 'payload' => $this->progressionPayload()])->refresh();
    $this->actingAs($this->leader)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertViewHas('config', fn ($config) => $config['peers'] === [['id' => $peer->id, 'revision' => $peer->revision, 'name' => 'Handelsfigur ('.$expected.')']]);
})->with('RpgInventoryControllerTest.ownerNames');
test('inventory search uses existing owner fields and keeps the team scope', function (string $search, bool $matches): void {
    $attributes = ['alias' => 'Nachtfalke', 'name' => 'Anzeigename', 'vorname' => 'Mara', 'nachname' => 'Wanderer'];
    $this->player->update($attributes);
    $this->character->update(['character_name' => 'Ruinenjäger']);
    $outsider = $this->progressionMember();
    $outsider->update($attributes);
    RpgCharacter::factory()->create(['user_id' => $outsider->id, 'character_name' => 'Ruinenjäger', 'payload' => $this->progressionPayload()]);
    $this->actingAs($this->leader)->get(route('rpg.inventory.index', ['search' => $search]))->assertOk()->assertViewHas('characters', fn ($characters) => $characters->pluck('id')->all() === ($matches ? [$this->character->id] : []));
})->with('RpgInventoryControllerTest.ownerSearches');
