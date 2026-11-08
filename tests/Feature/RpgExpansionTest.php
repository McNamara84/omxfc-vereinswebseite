<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCharacterSheetPresenter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgExpansionTestFixtures::class);
dataset('RpgExpansionTest.races', static fn (): array => [['Agarther'], ['Marsianer'], ['Morlock']]);
dataset('RpgExpansionTest.martianChoices', static fn (): array => [['Pilot', 'Nahkampf'], ['Wissenschaftler', 'Heimlichkeit'], ['Athletik', 'Beruf'], ['Unterhalten', 'Sprachen'], ['Pilot', 'Bildung']]);
test('new races are valid and store versioned sources', function (string $race): void {
    $data = $this->validate($this->payload($race));
    expect($data['character']['race'])->toBe($race)
        ->and(array_column($data['rules']['sources'], 'id'))->toBe(['base', 'expansion-1'])
        ->and($data['rules']['sources'][1]['author'])->toBe('Stefan Küppers')
        ->and($data['rules']['sources'][1]['version'])->toBe('1')
        ->and($data['creation']['attribute_race_modifiers']['in'] ?? 0)->toBe($race === 'Morlock' ? -1 : 0)
        ->and($data['attributes']['ro'])->toBe('0')
        ->and($data['creation']['advantage_budget']['used'])->toBe(0);
})->with('RpgExpansionTest.races');
test('new races require explicit source and their assigned culture', function (string $race): void {
    $payload = $this->payload($race);
    $payload['rule_sources']['expansion-1'] = '0';
    $this->assertRejected($payload, 'race');
    unset($payload['rule_sources']);
    $this->assertRejected($payload, 'race');
    $payload = $this->payload($race);
    $payload['culture'] = 'Landbewohner';
    $this->assertRejected($payload, 'culture');
})->with('RpgExpansionTest.races');
test('martian choices add to fixed grants without increasing pool budget', function (string $bonus, string $replacement): void {
    $data = $this->validate($this->payload('Marsianer', $bonus, $replacement));
    expect(array_sum($data['rule_choices']['skill_pools']['praekristofluu']))->toBe(12)
        ->and($data['rule_choices']['culture']['marsianer_bonus_skill'])->toBe($bonus)
        ->and($data['rule_choices']['marsianer_replacement_skill'])->toBe($replacement);
})->with('RpgExpansionTest.martianChoices');
test('martian choices reject unknown missing or forbidden values', function (): void {
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
});
test('martian intuition replacement requires kind zweier welten', function (): void {
    $payload = $this->payload('Marsianer', 'Pilot', 'Intuition');
    $this->assertRejected($payload, 'skills');
    $payload['advantages'][] = 'Kind zweier Welten';
    $payload['advantage_effects'] = [['name' => 'Kind zweier Welten', 'target' => '', 'justification' => '']];
    $data = $this->validate($payload);
    expect($data['advantages'])->toContain('Kind zweier Welten');
});
test('martian replacement rejects specializations even with matching pool entries', function (): void {
    foreach (['Beruf: Soldat', 'Kunde: Wetter', 'Unterhalten: Predigen'] as $replacement) {
        $payload = $this->payload('Marsianer', 'Pilot', $replacement);
        $this->assertRejected($payload, 'marsianer_replacement_skill');
    }
});
test('base race can explicitly disable expansion and store only base', function (): void {
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
    expect(array_column($data['rules']['sources'], 'id'))->toBe(['base']);
});
test('pool validation rejects missing foreign fractional and excess points', function (): void {
    foreach (['Agarther', 'Marsianer'] as $race) {
        foreach ([[], ['Feuerwaffen' => 2], ['Nahkampf' => 1.5], ['Nahkampf' => -1], ['Nahkampf' => 5], ['Nahkampf' => 1]] as $change) {
            $payload = $this->payload($race);
            $payload['praekristofluu_skill_points'] = $change === [] ? [] : array_replace($payload['praekristofluu_skill_points'], $change);
            try {
                $this->validate($payload);
                $this->fail('Ungültiger Pool wurde akzeptiert.');
            } catch (ValidationException $exception) {
                expect($exception->errors())->not->toBeEmpty();
            }
        }
    }
});
test('morlock racial disadvantage can only be removed with compensation', function (): void {
    $payload = $this->payload();
    $payload['disadvantages'] = [];
    $this->assertRejected($payload, 'disadvantages');
    $payload['negated_racial_disadvantages'] = ['Lichtscheu'];
    $data = $this->validate($payload);
    expect($data['creation']['advantage_budget']['used'])->toBe(1)
        ->and($data['disadvantages'])->toBe([]);
});
test('martian culture cannot be chosen by other races', function (): void {
    $payload = $this->payload('Agarther');
    $payload['culture'] = 'Marsianische Städter';
    $this->assertRejected($payload, 'culture');
});
test('unknown sources and invalid source values are rejected', function (): void {
    foreach ([['expansion-99' => 1], ['expansion-1' => 'yes'], ['base' => 0]] as $sources) {
        $payload = $this->payload();
        $payload['rule_sources'] = $sources;
        $this->assertRejected($payload, isset($sources['expansion-1']) ? 'rule_sources.expansion-1' : 'rule_sources');
    }
});
test('expansion trainings cost five points and respect source selection', function (): void {
    foreach (['Gladiator', 'Priester'] as $training) {
        $payload = $this->payload();
        // Spend five of the twenty paid FP on the chosen training.
        $payload['skills'] = array_values(array_filter($payload['skills'], fn ($skill) => ! in_array($skill['name'], ['Heiler', 'Reiten'])));
        $payload['skills'][] = ['name' => 'Reiten', 'value' => 3];
        $payload['skills'][] = ['name' => 'Unterhalten: '.($training === 'Gladiator' ? 'Kämpfen' : 'Predigen'), 'value' => 3];
        $payload['skills'][] = ['name' => $training === 'Gladiator' ? 'Intuition' : 'Bildung', 'value' => 2];
        $payload['trainings'] = [$training];
        $payload['training_allocations'] = [['training' => $training, 'skill' => 'Unterhalten: '.($training === 'Gladiator' ? 'Kämpfen' : 'Predigen'), 'points' => 3], ['training' => $training, 'skill' => $training === 'Gladiator' ? 'Intuition' : 'Bildung', 'points' => 2]];
        $data = $this->validate($payload);
        expect($data['trainings'])->toBe([$training]);
        $disabled = $payload;
        $disabled['race'] = 'Guul';
        $disabled['rule_sources']['expansion-1'] = '0';
        $this->assertRejected($disabled, 'trainings');
        $payload['training_allocations'][0]['points'] = 2;
        $this->assertRejected($payload, 'training_allocations');
    }
});
test('stored character and pdf keep sources and legacy pdf uses only base', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Admin->value]);
    $ag = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
    $ag->users()->attach($user, ['role' => Role::Mitglied->value]);
    $this->actingAs($user);
    $this->post(route('rpg.characters.store'), $this->payload('Marsianer'))->assertSessionHasNoErrors()->assertRedirect(route('rpg.characters.index'));
    $data = RpgCharacter::query()->firstOrFail()->payload;
    $sheet = (new RpgCharacterSheetPresenter)->present($data, []);
    expect($sheet['rule_sources'])->toBe('Basisregelwerk · 1. Erweiterung von Stefan Küppers')
        ->and(view('rpg.char-sheet', ['sheet' => $sheet])->render())->toContain('1. Erweiterung von Stefan Küppers');
    unset($data['rules']['sources']);
    $legacy = (new RpgCharacterSheetPresenter)->present($data, []);
    expect($legacy['rule_sources'])->toBe('Basisregelwerk');
});
test('source snapshots are independent of current catalog labels', function (): void {
    $data = $this->validate($this->payload());
    $data['rules']['sources'][1]['name'] = 'Gespeicherter Quellenname';
    $sheet = (new RpgCharacterSheetPresenter)->present($data, []);
    expect($sheet['rule_sources'])->toBe('Basisregelwerk · Gespeicherter Quellenname');
});
