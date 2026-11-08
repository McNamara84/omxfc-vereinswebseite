<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\RpgCharEditorRuleCatalog;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCharacterCreationV2TestFixtures::class);

test('v2 payload persists rule origin and one free advantage', function (): void {
    $data = $this->validate($this->payload(['advantages' => ['Zäh', 'Schnell'], 'advantage_effects' => [['name' => 'Schnell', 'target' => '', 'justification' => 'Nanotechnologie']]]));
    expect($data['rules'])->toBe(['edition' => 2007, 'creation_level' => 3, 'payload_version' => 2, 'sources' => RpgCharEditorRuleCatalog::snapshots([])])
        ->and($data['creation']['advantage_budget']['used'])->toBe(1)
        ->and($data['creation']['advantage_budget']['required_compensations'])->toBe(0)
        ->and($data['creation']['attribute_adjustments']['st'])->toBe(1)
        ->and($data['advantages'])->toContain('Schnell');
});

test('v2 rejects missing compensation and accepts gestaltwandler with two', function (): void {
    try {
        $this->validate($this->payload(['advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'], 'advantage_effects' => [['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]));
        $this->fail('Eine fehlende Kompensation wurde nicht abgelehnt.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('disadvantages');
    }
    $data = $this->validate($this->payload(['advantages' => ['Zäh', 'Gestaltwandler'], 'disadvantages' => ['Auffällig', 'Blutdurst'], 'advantage_effects' => [['name' => 'Gestaltwandler', 'target' => '', 'justification' => '']]]));
    expect($data['creation']['advantage_budget']['used'])->toBe(3)
        ->and($data['creation']['advantage_budget']['available_compensations'])->toBe(2);
});

test('v2 rejects client attribute value that disagrees with evaluator', function (): void {
    expect(fn () => $this->validate($this->payload(['attributes' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]])))->toThrow(ValidationException::class);
});

test('v2 ap trade and enhanced attribute are calculated server side', function (): void {
    $data = $this->validate($this->payload(['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1], 'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1, 'wi' => 1, 'wa' => 0, 'in' => 0, 'au' => -1], 'extra_ap_attribute' => 'au', 'advantages' => ['Zäh', 'Gesteigertes Attribut'], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation']]]));
    expect($data['creation']['attribute_budget']['used'])->toBe(3)
        ->and($data['creation']['attribute_budget']['extra'])->toBe(1)
        ->and($data['attributes']['wi'])->toBe('1');
});

test('v2 validates language capacity with and without sprachbegabt', function (): void {
    $skills = $this->completeSkillsForLevel(3, [['name' => 'Sprachen', 'value' => 2]]);
    try {
        $this->validate($this->payload(['skills' => $skills, 'languages' => ['Deutsch', 'Englisch', 'Französisch']]));
        $this->fail('Zu viele Sprachen ohne Sprachbegabt wurden nicht abgelehnt.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('languages');
    }
    $data = $this->validate($this->payload(['skills' => $skills, 'advantages' => ['Zäh', 'Sprachbegabt'], 'advantage_effects' => [['name' => 'Sprachbegabt', 'target' => '', 'justification' => '']], 'languages' => ['Deutsch', 'Englisch', 'Französisch', 'Schwedisch', 'Dänisch', 'Finnisch']]));
    expect($data['languages'])->toHaveCount(6);
});

test('v2 requires exactly four high tech items with advantage', function (): void {
    $threeHighTech = [['id' => 'fernglas', 'quantity' => 1], ['id' => 'funkgeraet', 'quantity' => 1], ['id' => 'gasmaske', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]];
    try {
        $this->validate($this->payload(['advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'advantage_effects' => [['name' => 'High-Tech-Ausrüstung', 'target' => '', 'justification' => '']], 'equipment_items' => $threeHighTech]));
        $this->fail('Drei High-Tech-Gegenstände wurden akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('equipment_items');
    }
    $fourHighTech = $threeHighTech;
    $fourHighTech[3] = ['id' => 'atemgeraet', 'quantity' => 1];
    $data = $this->validate($this->payload(['advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'advantage_effects' => [['name' => 'High-Tech-Ausrüstung', 'target' => '', 'justification' => '']], 'equipment_items' => $fourHighTech]));
    expect($data['equipment']['items'])->toHaveCount(6);
});

test('v2 rejects legacy natural weapon skill', function (): void {
    expect(fn () => $this->validate($this->payload(['skills' => [...$this->payload()['skills'], ['name' => 'Natürliche Waffen', 'value' => 1]], 'advantages' => ['Zäh', 'Natürliche Waffen'], 'advantage_effects' => [['name' => 'Natürliche Waffen', 'target' => '', 'justification' => 'Klauen']]])))->toThrow(ValidationException::class);
});

test('v2 volk der dreizehn inseln requires paid telepathy', function (): void {
    $skills = [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Athletik', 'value' => 1], ['name' => 'Beruf: Bauer', 'value' => 1], ['name' => 'Handeln', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4], ['name' => 'Heiler', 'value' => 4], ['name' => 'Pilot', 'value' => 4]];
    try {
        $this->validate($this->payload(['gender' => 'weiblich', 'culture' => 'Volk der 13 Inseln', 'skills' => $skills, 'advantages' => ['Zäh', 'Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Empathie', 'justification' => '']]]));
        $this->fail('Eine andere psychische Kraft als Telepathie wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('advantage_effects');
    }
    $data = $this->validate($this->payload(['gender' => 'weiblich', 'culture' => 'Volk der 13 Inseln', 'skills' => $skills, 'advantages' => ['Zäh', 'Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => '']]]));
    expect($data['creation']['advantage_budget']['used'])->toBe(1)
        ->and(collect($data['advantage_effects'])->firstWhere('name', 'Psychische Kraft')['target'])->toBe('Telepathie');
});

test('v2 accepts creation levels one to five and requires all seven adjustments', function (): void {
    foreach ([null, 0, 6] as $level) {
        $payload = $this->payload();
        if ($level === null) {
            unset($payload['figurenstaerke']);
        } else {
            $payload['figurenstaerke'] = $level;
        }
        try {
            $this->validate($payload);
            $this->fail('Eine ungültige Figurenstärke wurde akzeptiert.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('figurenstaerke');
        }
    }
    $levelPayloads = [1 => ['attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 1, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => []], 2 => ['attribute_adjustments' => ['st' => 1, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 2, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => []], 3 => [], 4 => [], 5 => []];
    foreach ($levelPayloads as $level => $overrides) {
        $data = $this->validate($this->payload(['figurenstaerke' => $level, ...$overrides]));
        expect($data['rules']['creation_level'])->toBe($level)
            ->and($data['creation']['level_rules']['level'])->toBe($level);
    }
    $payload = $this->payload();
    unset($payload['attribute_adjustments']['au']);
    try {
        $this->validate($payload);
        $this->fail('Eine unvollständige Attributherkunft wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('attribute_adjustments.au');
    }
});

test('v2 rejects unspent level dependent ap and fp budgets', function (): void {
    try {
        $this->validate($this->payload(['figurenstaerke' => 4, 'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]]));
        $this->fail('Ein nicht vollständig verteiltes AP-Budget wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['attributes'] ?? [])->toContain('Die verfügbaren Attributspunkte müssen vollständig verteilt werden.');
    }
    try {
        $this->validate($this->payload(['figurenstaerke' => 4, 'skills' => $this->completeSkillsForLevel(3)]));
        $this->fail('Ein nicht vollständig verteiltes FP-Budget wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['skills'] ?? [])->toContain('Die verfügbaren Fertigkeitspunkte müssen vollständig verteilt werden.');
    }
});

test('v2 applies level dependent skill caps and point budgets', function (): void {
    $levelOneBase = ['figurenstaerke' => 1, 'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 1, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => []];
    try {
        $this->validate($this->payload($levelOneBase + ['skills' => [...$this->payload()['skills'], ['name' => 'Handeln', 'value' => 4]]]));
        $this->fail('Ein FW über dem Maximum von Figurenstärke 1 wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('skills');
    }
    try {
        $this->validate($this->payload($levelOneBase + ['skills' => [...$this->payload()['skills'], ['name' => 'Handeln', 'value' => 3], ['name' => 'Fahren', 'value' => 3], ['name' => 'Feuerwaffen', 'value' => 3], ['name' => 'Heiler', 'value' => 2]]]));
        $this->fail('Mehr als 10 FP auf Figurenstärke 1 wurden akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('skills');
    }
    $levelFour = $this->validate($this->payload(['figurenstaerke' => 4, 'skills' => $this->completeSkillsForLevel(4, [['name' => 'Handeln', 'value' => 5]])]));
    $levelFive = $this->validate($this->payload(['figurenstaerke' => 5, 'skills' => $this->completeSkillsForLevel(5, [['name' => 'Handeln', 'value' => 6]])]));
    expect(collect($levelFour['skills'])->firstWhere('name', 'Handeln')['value'])->toBe('5')
        ->and(collect($levelFive['skills'])->firstWhere('name', 'Handeln')['value'])->toBe('6');
});

test('v2 applies level dependent training budgets', function (): void {
    $trainings = ['Arbeiter', 'Arzt (Heilkundiger)', 'Krieger'];
    try {
        $this->validate($this->payload(['figurenstaerke' => 1, 'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 1, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => [], 'trainings' => $trainings]));
        $this->fail('Ausbildungen für 15 FP wurden auf Figurenstärke 1 akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('trainings');
    }
    try {
        $this->validate($this->payload(['figurenstaerke' => 4, 'trainings' => $trainings]));
        $this->fail('Ausbildungen ohne Punkteverteilung wurden akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('training_allocations')
            ->and($exception->errors())->not->toHaveKey('trainings');
    }
});

test('v2 allows additive race and culture grants above the purchased skill cap', function (): void {
    $data = $this->validate($this->payload(['figurenstaerke' => 1, 'race' => 'Techno', 'culture' => 'Bunkermensch', 'barbar_attribute_bonus' => '', 'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => -1, 'ge' => 0, 'ro' => -1, 'wi' => 0, 'wa' => 0, 'in' => 1, 'au' => 0], 'techno_skill_points' => ['Fahren' => 2, 'Feuerwaffen' => 2, 'Heiler' => 2, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2], 'bunkermensch_bonus_skill' => 'Feuerwaffen', 'skills' => [['name' => 'Bildung', 'value' => 4], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 3], ['name' => 'Heiler', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2], ['name' => 'Athletik', 'value' => 3], ['name' => 'Fernkampf', 'value' => 3], ['name' => 'Handeln', 'value' => 3], ['name' => 'Unterhalten', 'value' => 1]], 'advantages' => [], 'disadvantages' => ['Tödliche Immunschwäche'], 'equipment_items' => [['id' => 'fernglas', 'quantity' => 1], ['id' => 'funkgeraet', 'quantity' => 1], ['id' => 'gasmaske', 'quantity' => 1], ['id' => 'atemgeraet', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1]]]));
    expect(collect($data['skills'])->firstWhere('name', 'Bildung')['value'])->toBe('4')
        ->and(collect($data['skills'])->firstWhere('name', 'Feuerwaffen')['value'])->toBe('3')
        ->and($data['disadvantages'])->toContain('Taratzenfutter')
        ->and($data['advantages'])->toContain('High-Tech-Ausrüstung');
});

test('v2 derives repeat counts and attribute bonuses from effect instances', function (): void {
    $data = $this->validate($this->payload(['attributes' => ['st' => 2, 'ge' => 1, 'ro' => 0, 'wi' => 1, 'wa' => 1, 'in' => 0, 'au' => 0], 'advantages' => ['Zäh', 'Gesteigertes Attribut'], 'disadvantages' => ['Auffällig'], 'advantage_counts' => ['Gesteigertes Attribut' => 20], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'], ['name' => 'Gesteigertes Attribut', 'target' => 'wa', 'justification' => 'Implantat']]]));
    expect($data['advantage_counts']['Gesteigertes Attribut'])->toBe(2)
        ->and($data['creation']['attribute_advantage_bonuses']['wi'])->toBe(1)
        ->and($data['creation']['attribute_advantage_bonuses']['wa'])->toBe(1)
        ->and($data['attributes']['wi'])->toBe('1')
        ->and($data['attributes']['wa'])->toBe('1');
});

test('v2 negates racial disadvantage and never counts remaining racial one as compensation', function (): void {
    $data = $this->validate($this->payload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'barbar_attribute_bonus' => '', 'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attributes' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1], 'skills' => [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1], ['name' => 'Athletik', 'value' => 4], ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Handeln', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4]], 'advantages' => ['Zäh'], 'disadvantages' => ['Gejagt'], 'negated_racial_disadvantages' => ['Primitiv']]));
    expect($data['disadvantages'])->not->toContain('Primitiv')
        ->toContain('Gejagt')
        ->and($data['creation']['advantage_budget']['used'])->toBe(1)
        ->and($data['creation']['advantage_budget']['available_compensations'])->toBe(0);
});

test('v2 rejects legacy language specializations and requires madness trigger', function (): void {
    try {
        $this->validate($this->payload(['skills' => [...$this->payload()['skills'], ['name' => 'Sprachen: Deutsch', 'value' => 1]]]));
        $this->fail('Eine alte Sprachspezialisierung wurde akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('skills');
    }
    try {
        $this->validate($this->payload(['disadvantages' => ['Anfälligkeit gegen Wahnsinn']]));
        $this->fail('Anfälligkeit gegen Wahnsinn wurde ohne Auslöser akzeptiert.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('disadvantage_details');
    }
    $data = $this->validate($this->payload(['disadvantages' => ['Anfälligkeit gegen Wahnsinn'], 'disadvantage_details' => ['Anfälligkeit gegen Wahnsinn' => 'Fleischverzehr']]));
    expect($data['disadvantage_details']['Anfälligkeit gegen Wahnsinn'])->toBe('Fleischverzehr');
});
