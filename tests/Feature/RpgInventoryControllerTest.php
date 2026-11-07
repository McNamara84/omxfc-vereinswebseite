<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgInventoryControllerTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
        $this->withoutExceptionHandling();
    }

    #[DataProvider('ownerNames')]
    public function test_inventory_index_displays_the_actual_owner_name(array $attributes, string $expected): void
    {
        $this->player->update($attributes);

        $this->actingAs($this->leader)->get(route('rpg.inventory.index'))
            ->assertOk()->assertSeeText($expected)
            ->assertViewHas('characters', fn ($characters) => $characters->count() === 1
                && $characters->first()->user->nicknameOrName() === $expected);
    }

    #[DataProvider('ownerNames')]
    public function test_inventory_counterparties_display_the_actual_owner_name(array $attributes, string $expected): void
    {
        $this->player->update($attributes);
        $peer = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'character_name' => 'Handelsfigur',
            'payload' => $this->progressionPayload()])->refresh();

        $this->actingAs($this->leader)->get(route('rpg.inventory.show', $this->character))
            ->assertOk()->assertViewHas('config', fn ($config) => $config['peers'] === [
                ['id' => $peer->id, 'revision' => $peer->revision, 'name' => 'Handelsfigur ('.$expected.')'],
            ]);
    }

    public static function ownerNames(): array
    {
        return [
            'alias takes priority' => [['alias' => ' Nachtfalke ', 'name' => 'Anzeigename', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Nachtfalke'],
            'name without alias' => [['alias' => ' ', 'name' => ' Anzeigename ', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Anzeigename'],
            'first and last name fallback' => [['alias' => null, 'name' => '', 'vorname' => 'Mara', 'nachname' => 'Wanderer'], 'Mara Wanderer'],
            'first name alone' => [['alias' => null, 'name' => '', 'vorname' => 'Mara', 'nachname' => ''], 'Mara'],
        ];
    }

    #[DataProvider('ownerSearches')]
    public function test_inventory_search_uses_existing_owner_fields_and_keeps_the_team_scope(string $search, bool $matches): void
    {
        $attributes = ['alias' => 'Nachtfalke', 'name' => 'Anzeigename', 'vorname' => 'Mara', 'nachname' => 'Wanderer'];
        $this->player->update($attributes);
        $this->character->update(['character_name' => 'Ruinenjäger']);
        $outsider = $this->progressionMember();
        $outsider->update($attributes);
        RpgCharacter::factory()->create(['user_id' => $outsider->id, 'character_name' => 'Ruinenjäger',
            'payload' => $this->progressionPayload()]);

        $this->actingAs($this->leader)->get(route('rpg.inventory.index', ['search' => $search]))
            ->assertOk()->assertViewHas('characters', fn ($characters) => $characters->pluck('id')->all()
                === ($matches ? [$this->character->id] : []));
    }

    public static function ownerSearches(): array
    {
        return [
            'character name' => ['Ruinenjäger', true],
            'alias' => ['Nachtfalke', true],
            'name' => ['Anzeigename', true],
            'first name' => ['Mara', true],
            'last name' => ['Wanderer', true],
            'partial alias and surrounding whitespace' => [' Nacht ', true],
            'no match' => ['GibtEsGarNicht', false],
        ];
    }
}
