<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCharacterSheetService;
use App\Support\RpgCharEditorSpecialRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

trait RpgCharEditorPdfTestFixtures
{
    use RefreshDatabase;

    private function validPdfPayload(array $overrides = []): array
    {
        $payload = array_replace_recursive(['figurenstaerke' => 3, 'player_name' => 'Spieler Eins', 'character_name' => 'Foo Bar', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => 'Ein erfahrener Charakter aus Wudan.', 'attributes' => ['st' => 2, 'ge' => 1], 'barbar_attribute_bonus' => 'st', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Athletik', 'value' => 4], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Handeln', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4]], 'advantages' => ['Zäh'], 'disadvantages' => ['Auffällig'], 'advantage_details' => [], 'disadvantage_details' => [], 'advantage_counts' => [], 'advantage_effects' => [], 'languages' => [], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]], 'equipment' => 'Messer, Seil, Feldflasche'], $overrides);
        if (! array_key_exists('attributes', $overrides)) {
            $payload['attributes'] = $this->validAttributesForRace((string) ($payload['race'] ?? ''));
        }
        foreach (['attributes', 'skills', 'advantages', 'disadvantages', 'advantage_details', 'disadvantage_details', 'advantage_counts', 'advantage_effects', 'languages', 'equipment_items'] as $listKey) {
            if (array_key_exists($listKey, $overrides)) {
                $payload[$listKey] = $overrides[$listKey];
            }
        }
        if (! array_key_exists('advantage_effects', $overrides)) {
            $payload['advantage_effects'] = $this->advantageEffectsForPayload($payload);
        }
        if (! array_key_exists('attribute_adjustments', $overrides)) {
            $payload['attributes'] = $this->completeAttributeBudgetForPayload($payload);
            $payload['attribute_adjustments'] = $this->attributeAdjustmentsForPayload($payload);
        }
        $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
        if (! array_key_exists('languages', $overrides)) {
            $languageValue = collect($payload['skills'])->firstWhere('name', 'Sprachen')['value'] ?? 0;
            $payload['languages'] = (int) $languageValue > 0 ? array_map(static fn (int $index): string => 'Testsprache '.$index, range(1, (int) $languageValue)) : [];
        }
        if (! array_key_exists('equipment_items', $overrides) && (in_array($payload['race'], ['Techno', 'Präkristofluu'], true) || in_array('High-Tech-Ausrüstung', $this->canonicalAdvantages($payload['advantages']), true))) {
            $payload['equipment_items'] = [['id' => 'fernglas', 'quantity' => 1], ['id' => 'funkgeraet', 'quantity' => 1], ['id' => 'gasmaske', 'quantity' => 1], ['id' => 'atemgeraet', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1]];
        }

        return $payload;
    }

    /** @return array<string, int> */
    private function completeAttributeBudgetForPayload(array $payload): array
    {
        $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];
        $levelRules = RpgCharEditorSpecialRules::creationLevel((int) ($payload['figurenstaerke'] ?? 3));
        $requiredPoints = (int) $levelRules['attributePoints'] + (trim((string) ($payload['extra_ap_attribute'] ?? '')) === '' ? 0 : 1);
        $modifiers = match ($payload['race'] ?? '') {
            'Guul' => ['au' => -1],
            'Nosfera' => ['ge' => 1, 'au' => -1],
            'Taratze' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
            'Wulfane' => ['ro' => 1, 'au' => -1],
            'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
            'Barbar' => [$payload['barbar_attribute_bonus'] ?? 'st' => 1],
            default => [],
        };
        $bonuses = [];
        foreach ($payload['advantage_effects'] ?? [] as $effect) {
            if (($effect['name'] ?? '') === 'Gesteigertes Attribut') {
                $target = (string) ($effect['target'] ?? '');
                $bonuses[$target] = ($bonuses[$target] ?? 0) + 1;
            }
        }
        $usedPoints = 0;
        foreach (RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS as $attribute) {
            $adjustment = (int) ($attributes[$attribute] ?? 0) - (int) ($modifiers[$attribute] ?? 0) - (int) ($bonuses[$attribute] ?? 0);
            $usedPoints += max($adjustment, 0);
        }
        foreach (RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS as $attribute) {
            if ($usedPoints >= $requiredPoints) {
                break;
            }
            if (array_key_exists($attribute, $attributes)) {
                continue;
            }
            $attributes[$attribute] = (int) ($modifiers[$attribute] ?? 0) + (int) ($bonuses[$attribute] ?? 0) + 1;
            $usedPoints++;
        }

        return $attributes;
    }

    /** @return list<array{name: string, value: int}> */
    private function completeSkillBudgetForPayload(array $payload): array
    {
        $skills = is_array($payload['skills'] ?? null) ? array_values($payload['skills']) : [];
        $levelRules = RpgCharEditorSpecialRules::creationLevel((int) ($payload['figurenstaerke'] ?? 3));
        $skillBudget = (int) $levelRules['skillPoints'];
        $skillMax = (int) $levelRules['skillMax'];
        $service = app(RpgCharacterSheetService::class);
        $validateCultureRequirements = new \ReflectionMethod($service, 'validateCultureRequirements');
        $freeSkillGrants = new \ReflectionMethod($service, 'freeSkillGrants');
        $skillPointsUsed = new \ReflectionMethod($service, 'skillPointsUsed');
        $skillPools = ['techno' => $payload['techno_skill_points'] ?? [], 'praekristofluu' => $payload['praekristofluu_skill_points'] ?? []];
        $cultureChoices = ['bunkermensch_bonus_skill' => $payload['bunkermensch_bonus_skill'] ?? '', 'mensch_21_first_bonus_skill' => $payload['mensch_21_first_bonus_skill'] ?? '', 'mensch_21_second_bonus_skill' => $payload['mensch_21_second_bonus_skill'] ?? ''];
        try {
            $validateCultureRequirements->invoke($service, (string) ($payload['culture'] ?? ''), $skills);
        } catch (ValidationException) {
            return $skills;
        }
        $fillers = ['Handeln', 'Heiler', 'Reiten', 'Unterhalten', 'Diebeskunst', 'Heimlichkeit', 'Fernkampf', 'Athletik', 'Fahren', 'Feuerwaffen', 'Pilot', 'Techniker'];
        foreach ($fillers as $filler) {
            try {
                $grants = $freeSkillGrants->invoke($service, (string) ($payload['race'] ?? ''), (string) ($payload['culture'] ?? ''), $skills, $skillPools, $cultureChoices, $skillMax);
            } catch (ValidationException) {
                return $skills;
            }
            $usedPoints = $skillPointsUsed->invoke($service, $skills, $grants);
            if ($usedPoints >= $skillBudget) {
                break;
            }
            if (in_array($filler, array_column($skills, 'name'), true)) {
                continue;
            }
            $skills[] = ['name' => $filler, 'value' => min($skillMax, $skillBudget - $usedPoints)];
        }

        return $skills;
    }

    private function canonicalAdvantages(array $advantages): array
    {
        $aliases = ['Zaeh' => 'Zäh', 'Natuerliche Waffen' => 'Natürliche Waffen', 'High-Tech-Ausruestung' => 'High-Tech-Ausrüstung'];

        return array_map(static fn (string $name): string => $aliases[$name] ?? $name, $advantages);
    }

    private function advantageEffectsForPayload(array $payload): array
    {
        $advantages = $this->canonicalAdvantages($payload['advantages'] ?? []);
        $racial = match ($payload['race'] ?? '') {
            'Guul' => ['Natürliche Waffen'],
            'Hydrit' => ['Kiemen', 'Natürliche Waffen'],
            'Nosfera' => ['Nachtsicht'],
            'Techno', 'Präkristofluu' => ['High-Tech-Ausrüstung'],
            default => [],
        };
        $details = $payload['advantage_details'] ?? [];
        $counts = $payload['advantage_counts'] ?? [];
        $effects = [];
        foreach (array_diff($advantages, ['Zäh', ...$racial]) as $advantage) {
            $count = in_array($advantage, ['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Panzerung', 'Regeneration'], true) ? max(1, (int) ($counts[$advantage] ?? 1)) : 1;
            $detail = trim((string) ($details[$advantage] ?? $details[str_replace(' ', '_', $advantage)] ?? ''));
            for ($index = 0; $index < $count; $index++) {
                $target = match ($advantage) {
                    'Gesteigertes Attribut' => $this->attributeTargetFromDetail($detail),
                    'Gesteigerter Sinn' => in_array($detail, ['Sehen', 'Hören', 'Riechen', 'Schmecken', 'Tasten'], true) ? $detail : 'Sehen',
                    'Psychische Kraft' => 'Telepathie',
                    default => '',
                };
                $requiresReason = in_array($advantage, ['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Nachtsicht', 'Natürliche Waffen', 'Panzerung', 'Regeneration', 'Schnell', 'Tiergefährte'], true);
                $effects[] = ['name' => $advantage, 'target' => $target, 'justification' => $requiresReason ? $detail !== '' ? $detail : 'Testbegründung' : ''];
            }
        }

        return $effects;
    }

    private function attributeTargetFromDetail(string $detail): string
    {
        $labels = ['ST' => 'st', 'GE' => 'ge', 'RO' => 'ro', 'WI' => 'wi', 'WA' => 'wa', 'IN' => 'in', 'AU' => 'au'];
        foreach ($labels as $label => $target) {
            if (str_contains(mb_strtoupper($detail), $label)) {
                return $target;
            }
        }

        return 'wi';
    }

    private function attributeAdjustmentsForPayload(array $payload): array
    {
        $modifiers = match ($payload['race'] ?? '') {
            'Guul' => ['au' => -1],
            'Nosfera' => ['ge' => 1, 'au' => -1],
            'Taratze' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
            'Wulfane' => ['ro' => 1, 'au' => -1],
            'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
            'Barbar' => [$payload['barbar_attribute_bonus'] ?? 'st' => 1],
            default => [],
        };
        $bonuses = [];
        foreach ($payload['advantage_effects'] ?? [] as $effect) {
            if (($effect['name'] ?? '') === 'Gesteigertes Attribut') {
                $target = (string) ($effect['target'] ?? '');
                $bonuses[$target] = ($bonuses[$target] ?? 0) + 1;
            }
        }
        $adjustments = [];
        foreach (['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'] as $attribute) {
            $adjustments[$attribute] = max(-1, min(1, (int) ($payload['attributes'][$attribute] ?? 0) - (int) ($modifiers[$attribute] ?? 0) - (int) ($bonuses[$attribute] ?? 0)));
        }

        return $adjustments;
    }

    private function validAttributesForRace(string $race): array
    {
        return match (trim($race)) {
            'Barbar' => ['st' => 2, 'ge' => 1],
            'Guul' => ['st' => 1, 'ge' => 1, 'au' => -1],
            'Nosfera' => ['st' => 1, 'ge' => 2, 'au' => -1],
            'Taratze' => ['st' => 2, 'ge' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
            'Wulfane' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'au' => -1],
            'Techno' => ['st' => 0, 'ge' => 1, 'ro' => -1, 'in' => 1],
            default => ['st' => 1, 'ge' => 1],
        };
    }

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        }
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

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Nebenverein', 'personal_team' => false]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }
}
