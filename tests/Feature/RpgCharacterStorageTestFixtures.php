<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait RpgCharacterStorageTestFixtures
{
    use RefreshDatabase;

    private function validCharacterPayload(array $overrides = []): array
    {
        $payload = array_replace_recursive(['figurenstaerke' => 3, 'player_name' => 'Spieler Eins', 'character_name' => 'Foo Bar', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => 'Ein erfahrener Charakter aus Wudan.', 'attributes' => ['st' => 2, 'ge' => 1], 'barbar_attribute_bonus' => 'st', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Ueberleben', 'value' => 1], ['name' => 'Athletik', 'value' => 4], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzuechter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Handeln', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4]], 'advantages' => ['Zaeh'], 'disadvantages' => ['Auffaellig'], 'advantage_effects' => [], 'languages' => [], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]], 'equipment' => 'Messer, Seil, Feldflasche'], $overrides);
        foreach (['attributes', 'skills', 'advantages', 'disadvantages', 'advantage_effects', 'languages', 'equipment_items'] as $listKey) {
            if (array_key_exists($listKey, $overrides)) {
                $payload[$listKey] = $overrides[$listKey];
            }
        }
        $modifiers = match ($payload['race'] ?? '') {
            'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
            'Barbar' => [$payload['barbar_attribute_bonus'] ?? 'st' => 1],
            default => [],
        };
        $payload['attribute_adjustments'] = [];
        foreach (['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'] as $attribute) {
            $payload['attribute_adjustments'][$attribute] = (int) ($payload['attributes'][$attribute] ?? 0) - (int) ($modifiers[$attribute] ?? 0);
        }
        if (! array_key_exists('equipment_items', $overrides) && in_array($payload['race'], ['Techno', 'Präkristofluu'], true)) {
            $payload['equipment_items'] = [['id' => 'fernglas', 'quantity' => 1], ['id' => 'funkgeraet', 'quantity' => 1], ['id' => 'gasmaske', 'quantity' => 1], ['id' => 'atemgeraet', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1]];
        }

        return $payload;
    }

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role instanceof Role ? $role->value : $role]);

        return $user->refresh();
    }

    private function addAgRollenspielMembership(User $user): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'AG Rollenspiel', 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user->refresh();
    }

    private function actingAgMember(int $points = 0): User
    {
        $user = $this->addAgRollenspielMembership($this->createMember());
        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }
        $this->actingAs($user->refresh());

        return $user->refresh();
    }
}
