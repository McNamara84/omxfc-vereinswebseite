<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCharacterProgressionAdapter;
use App\Services\RpgExperienceAwardCalculator;
use App\Services\RpgInventoryAdapter;
use App\Support\RpgCharEditorEquipment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgProgressionRulesTestFixtures::class);
dataset('RpgProgressionRulesTest.durations', static fn (): array => [[359, 1], [360, 2], [719, 2], [720, 3], [7200, 21]]);
dataset('RpgProgressionRulesTest.invalidOperations', static fn (): array => [[['type' => 'attribute', 'name' => 'unknown']], [['type' => 'advantage', 'name' => 'Unknown']], [['type' => 'skill', 'name' => 'Wissenschaftler', 'steps' => 2]], [['type' => 'skill', 'name' => 'Unknown']], [['type' => 'skill', 'name' => 'Nahkampf', 'steps' => -1]], [['type' => 'skill', 'name' => 'Nahkampf', 'reason' => ' ']], [['type' => 'advantage', 'name' => 'Gesteigerter Sinn', 'target' => 'unknown']], [['type' => 'advantage', 'name' => 'High-Tech-Ausrüstung']], [['type' => 'advantage', 'name' => 'Panzerung', 'steps' => 2]], [['type' => 'skill', 'name' => 'Nahkampf', 'cost' => 0]], [['type' => 'skill', 'name' => 'Nahkampf', 'target' => 'st']], [['type' => 'skill', 'name' => 'Nahkampf', 'items' => ['a', 'b', 'c', 'd']]], [['type' => 'skill', 'name' => 'Nahkampf', 'languages' => ['Englisch']]], [['type' => 'advantage', 'name' => 'Panzerung', 'items' => ['a', 'b', 'c', 'd']]], [['type' => 'advantage', 'name' => 'High-Tech-Ausrüstung', 'items' => ['invalid', 'invalid', 'invalid', 'invalid']]], [['type' => 'skill', 'name' => 'Sprachen', 'languages' => ['Englisch', 'Englisch']]]]);
dataset('RpgProgressionRulesTest.invalidAwards', static fn (): array => [[['points' => -1]], [['points' => 1.5]], [['roleplay' => 3]], [['humor' => 2]], [['points' => 10, 'reason' => '   ']]]);
test('awards round duration down without an eleven point cap', function (int $minutes, int $points): void {
    $result = app(RpgExperienceAwardCalculator::class)->calculate($minutes, 0, ['survived' => true, 'roleplay' => 0, 'humor' => false, 'rescue' => false, 'unwounded' => false]);
    expect($result['points'])->toBe($points);
})->with('RpgProgressionRulesTest.durations');
test('award criteria and override', function (): void {
    $input = ['survived' => true, 'roleplay' => 2, 'humor' => true, 'rescue' => true, 'unwounded' => true];
    $result = app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input);
    expect($result['points'])->toBe(11)
        ->and(app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input + ['points' => 0, 'reason' => 'Nachteile umgangen'])['points'])->toBe(0)
        ->and(fn () => app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input + ['points' => 0]))->toThrow(ValidationException::class);
});
test('multiple skill steps have cumulative cost and ignore creation limit', function (): void {
    $result = $this->evaluate([$this->operation(steps: 4)]);
    expect($result['cost'])->toBe(48)
        ->and($result['changes'][0]['after'])->toBe(6);
});
test('attributes and targeted advantages use separate limits', function (): void {
    $result = $this->evaluate([$this->operation('attribute', 'st'), $this->operation('advantage', 'Gesteigertes Attribut', extra: ['target' => 'st'])]);
    expect($result['cost'])->toBe(50)
        ->and($result['payload']['attributes']['st'])->toBe(2)
        ->and(fn () => $this->evaluate([$this->operation('attribute', 'st', 2)]))->toThrow(ValidationException::class);
});
test('expansion race modifier is respected', function (): void {
    $payload = $this->progressionPayload();
    $payload['character']['race'] = 'Morlock';
    $payload['attributes']['in'] = -1;
    $result = $this->evaluate([$this->operation('attribute', 'in')], $payload);
    expect($result['payload']['attributes']['in'])->toBe(0)
        ->and(fn () => $this->evaluate([$this->operation('attribute', 'in', 2)], $payload))->toThrow(ValidationException::class);
});
test('repeatable advantages and shapechanger price', function (): void {
    $result = $this->evaluate([$this->operation('advantage', 'Panzerung'), $this->operation('advantage', 'Panzerung'), $this->operation('advantage', 'Gestaltwandler')]);
    expect($result['cost'])->toBe(100)
        ->and($result['payload']['advantage_counts']['Panzerung'])->toBe(2)
        ->and(fn () => $this->evaluate([$this->operation('advantage', 'Zäh'), $this->operation('advantage', 'Zäh')]))->toThrow(ValidationException::class);
});
test('disadvantage removal does not refund creation budget', function (): void {
    $payload = $this->progressionPayload();
    $payload['creation'] = ['attribute_budget' => ['used' => 2]];
    $result = $this->evaluate([$this->operation('disadvantage', 'Lichtscheu')], $payload);
    expect($result['cost'])->toBe(20)
        ->and($result['payload']['disadvantages'])->toBe([])
        ->and($result['payload']['creation'])->toBe($payload['creation'])
        ->and(fn () => $this->evaluate([$this->operation('disadvantage', 'Lichtscheu'), $this->operation('disadvantage', 'Lichtscheu')]))->toThrow(ValidationException::class);
});
test('psychic skills require power and cost twice as much', function (): void {
    $result = $this->evaluate([$this->operation('advantage', 'Psychische Kraft', extra: ['target' => 'Telepathie']), $this->operation('skill', 'Telepathie', 4)]);
    expect($result['cost'])->toBe(80)
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Telepathie')]))->toThrow(ValidationException::class);
});
test('related operations can establish prerequisites', function (): void {
    $result = $this->evaluate([$this->operation('advantage', 'Kind zweier Welten'), $this->operation('skill', 'Intuition')]);
    expect($result['cost'])->toBe(26)
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Intuition')]))->toThrow(ValidationException::class);
});
test('new languages must be named and existing languages preserved', function (): void {
    $result = $this->evaluate([$this->operation('skill', 'Sprachen', extra: ['languages' => ['Französisch']])]);
    expect($result['payload']['languages'])->toBe(['Französisch'])
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Sprachen')]))->toThrow(ValidationException::class);
});
test('high tech purchase merges inventory and preserves existing notes', function (): void {
    $items = array_values(array_filter(RpgCharEditorEquipment::items(), RpgCharEditorEquipment::requiresHighTechAdvantage(...)));
    $id = $items[0]['id'];
    $payload = $this->progressionPayload();
    $payload['equipment'] = ['notes' => 'Persönlicher Gegenstand', 'items' => [['id' => $id, 'name' => $items[0]['name'], 'quantity' => 1]]];
    $payload = app(RpgInventoryAdapter::class)->initializeNew($payload);
    $result = $this->evaluate([$this->operation('advantage', 'High-Tech-Ausrüstung', extra: ['items' => array_fill(0, 4, $id)])], $payload);
    expect($result['cost'])->toBe(20)
        ->and($result['payload']['equipment']['items'])->toHaveCount(1)
        ->and($result['payload']['equipment']['items'][0]['quantity'])->toBe(5)
        ->and($result['payload']['equipment']['notes'])->toBe('Persönlicher Gegenstand')
        ->and($result['payload']['equipment'])->toHaveKey('ammunition');
});
test('unique targets and repeatable regeneration', function (): void {
    $first = $this->operation('advantage', 'Gesteigerter Sinn', extra: ['target' => 'Sehen']);
    $second = $this->operation('advantage', 'Gesteigerter Sinn', extra: ['target' => 'Hören']);
    $result = $this->evaluate([$first, $second, $this->operation('advantage', 'Regeneration'), $this->operation('advantage', 'Regeneration')]);
    expect($result['payload']['advantage_counts']['Regeneration'])->toBe(2)
        ->and($result['payload']['advantage_counts']['Gesteigerter Sinn'])->toBe(2)
        ->and(fn () => $this->evaluate([$first, $first]))->toThrow(ValidationException::class);
});
test('primitive must be removed before learning education', function (): void {
    $payload = $this->progressionPayload();
    $payload['skills'] = [];
    $payload['disadvantages'] = ['Primitiv'];
    $result = $this->evaluate([$this->operation('disadvantage', 'Primitiv'), $this->operation('skill', 'Bildung')], $payload);
    expect($result['cost'])->toBe(26)
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Bildung')], $payload))->toThrow(ValidationException::class);
});
test('specialized science cannot exceed education', function (): void {
    $result = $this->evaluate([$this->operation('skill', 'Wissenschaftler: Medizin')]);
    expect($result['cost'])->toBe(6)
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Wissenschaftler: Medizin', 2)]))->toThrow(ValidationException::class);
});
test('language capacity can expand but known languages cannot be replaced', function (): void {
    $payload = $this->progressionPayload();
    $payload['skills'][] = ['name' => 'Sprachen', 'value' => 1];
    $payload['languages'] = ['Englisch'];
    $result = $this->evaluate([$this->operation('advantage', 'Sprachbegabt', extra: ['languages' => ['Englisch', 'Französisch', 'Deutsch']])], $payload);
    expect($result['payload']['languages'])->toHaveCount(3)
        ->and(fn () => $this->evaluate([$this->operation('skill', 'Sprachen', extra: ['languages' => ['Deutsch', 'Französisch']])], $payload))->toThrow(ValidationException::class);
});
test('legacy aliases counts and original creation values are preserved', function (): void {
    $payload = $this->progressionPayload();
    $payload['advantages'] = ['Zaeh', 'Panzerung'];
    $payload['advantage_counts'] = ['Panzerung' => 2];
    $payload['creation'] = ['attribute_race_modifiers' => ['st' => 1], 'origin' => 'Original'];
    $payload['attributes']['st'] = -2;
    $result = $this->evaluate([$this->operation('attribute', 'st', 3)], $payload);
    expect($result['payload']['advantages'])->toContain('Zäh')
        ->and($result['payload']['advantage_effects'])->toHaveCount(3)
        ->and($result['payload']['attributes']['st'])->toBe(1)
        ->and($result['payload']['creation'])->toBe($payload['creation'])
        ->and(fn () => $this->evaluate([$this->operation('advantage', 'Zäh')], $payload))->toThrow(ValidationException::class);
});
test('unambiguous barbar origin can be recovered but ambiguous origin is blocked', function (): void {
    $payload = $this->progressionPayload();
    $payload['character']['race'] = 'Barbar';
    $payload['attributes']['st'] = 2;
    $adapter = new RpgCharacterProgressionAdapter;
    expect($adapter->raceModifiers($adapter->normalize($payload)))->toBe(['st' => 1]);
    $payload['attributes']['st'] = 1;
    expect(fn () => $this->evaluate([$this->operation('attribute', 'ge')], $payload))->toThrow(ValidationException::class);
});
test('unknown legacy attribute bonus requires clarification', function (): void {
    $payload = $this->progressionPayload();
    $payload['advantages'] = ['Gesteigertes Attribut'];
    expect(fn () => $this->evaluate([$this->operation('attribute', 'st')], $payload))->toThrow(ValidationException::class);
});
test('invalid award values are rejected', function (array $input): void {
    expect(fn () => app(RpgExperienceAwardCalculator::class)->calculate(360, 0, $input + ['survived' => true, 'roleplay' => 0, 'humor' => false, 'rescue' => false, 'unwounded' => false]))->toThrow(ValidationException::class);
})->with('RpgProgressionRulesTest.invalidAwards');
test('every roleplay and cycle combination', function (): void {
    foreach ([0, 1, 2] as $roleplay) {
        foreach ([0, 1, 2, 4] as $cycle) {
            $result = app(RpgExperienceAwardCalculator::class)->calculate(0, $cycle, ['survived' => false, 'roleplay' => $roleplay, 'humor' => true, 'rescue' => true, 'unwounded' => true]);
            expect($result['points'])->toBe($roleplay + $cycle + 3);
        }
    }
});
test('invalid improvements are rejected', function (array $operation): void {
    expect(fn () => $this->evaluate([$operation + ['reason' => 'Begründung']]))->toThrow(ValidationException::class);
})->with('RpgProgressionRulesTest.invalidOperations');
