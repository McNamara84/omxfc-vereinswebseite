<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgCharacterSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RpgExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $race = 'Morlock', string $bonus = 'Pilot', string $replacement = 'Nahkampf'): array
    {
        $adjustments = ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
        $attributes = $adjustments;
        $pool = [];
        if ($race === 'Morlock') {
            $attributes['in'] = -1;
            $grants = ['Heimlichkeit' => 2, 'Überleben' => 1, 'Diebeskunst' => 1, 'Nahkampf' => 1];
            $culture = 'Ruinenbewohner';
        } else {
            $culture = $race === 'Marsianer' ? 'Marsianische Städter' : 'Mensch des 21. Jahrhunderts';
            $pool = ['Bildung' => 2, 'Fahren' => 2, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2];
            $pool[$race === 'Agarther' ? 'Nahkampf' : $replacement] = ($pool[$replacement] ?? 0) + 2;
            $grants = ['Beruf' => 3];
            foreach ($pool as $skill => $points) {
                $grants[$skill] = ($grants[$skill] ?? 0) + $points;
            }
            foreach ($race === 'Agarther' ? ['Beruf', 'Bildung', 'Pilot'] : ['Bildung', 'Techniker', $bonus] as $skill) {
                $grants[$skill] = ($grants[$skill] ?? 0) + 1;
            }
        }

        $skills = $grants;
        $paid = 20;
        foreach (['Heiler', 'Fernkampf', 'Handeln', 'Athletik', 'Reiten', 'Unterhalten', 'Feuerwaffen'] as $skill) {
            if ($paid === 0) {
                break;
            }
            if (! isset($skills[$skill])) {
                $skills[$skill] = min(4, $paid);
                $paid -= $skills[$skill];
            }
        }
        $this->assertSame(0, $paid);

        return [
            'figurenstaerke' => 3, 'player_name' => 'Regeltest', 'character_name' => $race.' Test', 'gender' => 'divers',
            'race' => $race, 'culture' => $culture, 'rule_sources' => ['expansion-1' => '1'],
            'attributes' => $attributes, 'attribute_adjustments' => $adjustments,
            'skills' => array_map(static fn ($name, $value) => compact('name', 'value'), array_keys($skills), array_values($skills)),
            'advantages' => $race === 'Morlock' ? ['Zäh', 'Nachtsicht'] : ['Zäh', 'High-Tech-Ausrüstung'],
            'disadvantages' => $race === 'Morlock' ? ['Lichtscheu'] : [],
            'praekristofluu_skill_points' => $pool,
            'mensch_21_first_bonus_skill' => 'Bildung', 'mensch_21_second_bonus_skill' => 'Pilot',
            'marsianer_bonus_skill' => $bonus, 'marsianer_replacement_skill' => $replacement,
            'languages' => $replacement === 'Sprachen' ? ['Marsianisch', 'Englisch'] : [],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => array_map(static fn ($id) => ['id' => $id, 'quantity' => 1], $race === 'Morlock'
                ? ['messer-dolch', 'seil', 'rucksack', 'wasserschlauch', 'wochenration', 'bogen']
                : ['fernglas', 'funkgeraet', 'gasmaske', 'atemgeraet', 'seil', 'rucksack']),
        ];
    }

    private function validate(array $payload): array
    {
        return app(RpgCharacterSheetService::class)->validatedPdfPayload(Request::create('/', 'POST', $payload));
    }

    private function assertRejected(array $payload, string $field): void
    {
        try {
            $this->validate($payload);
            $this->fail('Ungültiger Erweiterungscharakter wurde akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors(), json_encode($exception->errors(), JSON_UNESCAPED_UNICODE));
        }
    }

    public static function races(): array
    {
        return [['Agarther'], ['Marsianer'], ['Morlock']];
    }

    #[DataProvider('races')]
    public function test_new_races_are_valid_and_store_versioned_sources(string $race): void
    {
        $data = $this->validate($this->payload($race));
        $this->assertSame($race, $data['character']['race']);
        $this->assertSame(['base', 'expansion-1'], array_column($data['rules']['sources'], 'id'));
        $this->assertSame('Stefan Küppers', $data['rules']['sources'][1]['author']);
        $this->assertSame('1', $data['rules']['sources'][1]['version']);
        $this->assertSame($race === 'Morlock' ? -1 : 0, $data['creation']['attribute_race_modifiers']['in'] ?? 0);
        $this->assertSame('0', $data['attributes']['ro']);
        $this->assertSame(0, $data['creation']['advantage_budget']['used']);
    }

    #[DataProvider('races')]
    public function test_new_races_require_explicit_source_and_their_assigned_culture(string $race): void
    {
        $payload = $this->payload($race);
        $payload['rule_sources']['expansion-1'] = '0';
        $this->assertRejected($payload, 'race');
        unset($payload['rule_sources']);
        $this->assertRejected($payload, 'race');
        $payload = $this->payload($race);
        $payload['culture'] = 'Landbewohner';
        $this->assertRejected($payload, 'culture');
    }

    public static function martianChoices(): array
    {
        return [['Pilot', 'Nahkampf'], ['Wissenschaftler', 'Heimlichkeit'], ['Athletik', 'Beruf'], ['Unterhalten', 'Sprachen'], ['Pilot', 'Bildung']];
    }

    #[DataProvider('martianChoices')]
    public function test_martian_choices_add_to_fixed_grants_without_increasing_pool_budget(string $bonus, string $replacement): void
    {
        $data = $this->validate($this->payload('Marsianer', $bonus, $replacement));
        $this->assertSame(12, array_sum($data['rule_choices']['skill_pools']['praekristofluu']));
        $this->assertSame($bonus, $data['rule_choices']['culture']['marsianer_bonus_skill']);
        $this->assertSame($replacement, $data['rule_choices']['marsianer_replacement_skill']);
    }

    public function test_martian_choices_reject_unknown_missing_or_forbidden_values(): void
    {
        foreach (['', 'Feuerwaffen', 'Zauberei'] as $replacement) {
            $payload = $this->payload('Marsianer');
            $payload['marsianer_replacement_skill'] = $replacement;
            $this->assertRejected($payload, 'marsianer_replacement_skill');
        }
        foreach (['', 'Nahkampf', 'Techniker', 'Zauberei'] as $bonus) {
            $payload = $this->payload('Marsianer');
            $payload['marsianer_bonus_skill'] = $bonus;
            $this->assertRejected($payload, 'marsianer_bonus_skill');
        }
    }

    public function test_martian_intuition_replacement_requires_kind_zweier_welten(): void
    {
        $payload = $this->payload('Marsianer', 'Pilot', 'Intuition');
        $this->assertRejected($payload, 'skills');
        $payload['advantages'][] = 'Kind zweier Welten';
        $payload['advantage_effects'] = [['name' => 'Kind zweier Welten', 'target' => '', 'justification' => '']];
        $data = $this->validate($payload);
        $this->assertContains('Kind zweier Welten', $data['advantages']);
    }

    public function test_base_race_can_explicitly_disable_expansion_and_store_only_base(): void
    {
        $payload = $this->payload('Agarther');
        $payload['race'] = 'Präkristofluu';
        $payload['rule_sources']['expansion-1'] = '0';
        $payload['praekristofluu_skill_points']['Feuerwaffen'] = $payload['praekristofluu_skill_points']['Nahkampf'];
        unset($payload['praekristofluu_skill_points']['Nahkampf']);
        foreach ($payload['skills'] as $index => $skill) {
            if ($skill['name'] === 'Nahkampf') {
                $payload['skills'][$index]['name'] = 'Feuerwaffen';
            }
        }
        $data = $this->validate($payload);
        $this->assertSame(['base'], array_column($data['rules']['sources'], 'id'));
    }

    public function test_pool_validation_rejects_missing_foreign_fractional_and_excess_points(): void
    {
        foreach (['Agarther', 'Marsianer'] as $race) {
            foreach ([[], ['Feuerwaffen' => 2], ['Nahkampf' => 1.5], ['Nahkampf' => -1], ['Nahkampf' => 5], ['Nahkampf' => 1]] as $change) {
                $payload = $this->payload($race);
                $payload['praekristofluu_skill_points'] = $change === [] ? [] : array_replace($payload['praekristofluu_skill_points'], $change);
                try {
                    $this->validate($payload);
                    $this->fail('Ungültiger Pool wurde akzeptiert.');
                } catch (ValidationException $exception) {
                    $this->assertNotEmpty($exception->errors());
                }
            }
        }
    }

    public function test_morlock_racial_disadvantage_can_only_be_removed_with_compensation(): void
    {
        $payload = $this->payload();
        $payload['disadvantages'] = [];
        $this->assertRejected($payload, 'disadvantages');
        $payload['negated_racial_disadvantages'] = ['Lichtscheu'];
        $data = $this->validate($payload);
        $this->assertSame(1, $data['creation']['advantage_budget']['used']);
        $this->assertSame([], $data['disadvantages']);
    }

    public function test_martian_culture_cannot_be_chosen_by_other_races(): void
    {
        $payload = $this->payload('Agarther');
        $payload['culture'] = 'Marsianische Städter';
        $this->assertRejected($payload, 'culture');
    }

    public function test_unknown_sources_and_invalid_source_values_are_rejected(): void
    {
        foreach ([['expansion-99' => 1], ['expansion-1' => 'yes'], ['base' => 0]] as $sources) {
            $payload = $this->payload();
            $payload['rule_sources'] = $sources;
            $this->assertRejected($payload, isset($sources['expansion-1']) ? 'rule_sources.expansion-1' : 'rule_sources');
        }
    }

    public function test_expansion_trainings_cost_five_points_and_respect_source_selection(): void
    {
        foreach (['Gladiator', 'Priester'] as $training) {
            $payload = $this->payload();
            // Spend five of the twenty paid FP on the chosen training.
            $payload['skills'] = array_values(array_filter($payload['skills'], fn ($skill) => ! in_array($skill['name'], ['Heiler', 'Reiten'])));
            $payload['skills'][] = ['name' => 'Reiten', 'value' => 3];
            $payload['skills'][] = ['name' => 'Unterhalten: '.($training === 'Gladiator' ? 'Kämpfen' : 'Predigen'), 'value' => 3];
            $payload['skills'][] = ['name' => $training === 'Gladiator' ? 'Intuition' : 'Bildung', 'value' => 2];
            $payload['trainings'] = [$training];
            $payload['training_allocations'] = [
                ['training' => $training, 'skill' => 'Unterhalten: '.($training === 'Gladiator' ? 'Kämpfen' : 'Predigen'), 'points' => 3],
                ['training' => $training, 'skill' => $training === 'Gladiator' ? 'Intuition' : 'Bildung', 'points' => 2],
            ];
            $data = $this->validate($payload);
            $this->assertSame([$training], $data['trainings']);
            $disabled = $payload;
            $disabled['race'] = 'Guul';
            $disabled['rule_sources']['expansion-1'] = '0';
            $this->assertRejected($disabled, 'trainings');
            $payload['training_allocations'][0]['points'] = 2;
            $this->assertRejected($payload, 'training_allocations');
        }
    }

    public function test_stored_character_and_pdf_keep_sources_and_legacy_pdf_uses_only_base(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);
        $ag = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
        $ag->users()->attach($user, ['role' => Role::Mitglied->value]);
        $this->actingAs($user);
        $this->post(route('rpg.characters.store'), $this->payload('Marsianer'))->assertSessionHasNoErrors()->assertRedirect(route('rpg.characters.index'));
        $data = RpgCharacter::query()->firstOrFail()->payload;
        $sheet = (new RpgCharacterSheetPresenter)->present($data, []);
        $this->assertSame('Basisregelwerk · 1. Erweiterung von Stefan Küppers', $sheet['rule_sources']);
        $this->assertStringContainsString('1. Erweiterung von Stefan Küppers', view('rpg.char-sheet', ['sheet' => $sheet])->render());
        unset($data['rules']['sources']);
        $legacy = (new RpgCharacterSheetPresenter)->present($data, []);
        $this->assertSame('Basisregelwerk', $legacy['rule_sources']);
    }

    public function test_source_snapshots_are_independent_of_current_catalog_labels(): void
    {
        $data = $this->validate($this->payload());
        $data['rules']['sources'][1]['name'] = 'Gespeicherter Quellenname';
        $sheet = (new RpgCharacterSheetPresenter)->present($data, []);
        $this->assertSame('Basisregelwerk · Gespeicherter Quellenname', $sheet['rule_sources']);
    }
}
