<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RpgCharEditorRuleCatalog as Catalog;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('javascript fixture matches the shared server definitions', function (): void {
    expect(json_decode(file_get_contents(__DIR__.'/../Fixtures/rpg-extension-rules.json'), true, flags: JSON_THROW_ON_ERROR))->toBe(['ruleCatalog' => Catalog::ruleConfig(), 'trainingRules' => RpgCharEditorTraining::ruleConfig()]);
});

test('sources are complete and disabled expansions are not snapshotted', function (): void {
    expect(Catalog::sources()['expansion-1']['defaultEnabled'])->toBeTrue()
        ->and(array_column(Catalog::snapshots([]), 'id'))->toBe(['base'])
        ->and(array_column(Catalog::snapshots(['expansion-1' => false]), 'id'))->toBe(['base']);
    $snapshots = Catalog::snapshots(['expansion-1' => true]);
    expect(array_column($snapshots, 'id'))->toBe(['base', 'expansion-1'])
        ->and($snapshots[1]['author'])->toBe('Stefan Küppers')
        ->and($snapshots[1])->not->toHaveKey('defaultEnabled');
    foreach ([...Catalog::races(), ...Catalog::cultures(), ...RpgCharEditorTraining::definitions()] as $definition) {
        expect(Catalog::sources())->toHaveKey($definition['source']);
    }
});

test('exclusive cultures and baseline restrictions remain consistent', function (): void {
    expect(Catalog::allowedCultures('Morlock'))->toBe(['Ruinenbewohner'])
        ->and(Catalog::allowedCultures('Marsianer'))->toBe(['Marsianische Städter'])
        ->and(Catalog::allowedCultures('Agarther'))->toBe(['Mensch des 21. Jahrhunderts'])
        ->and(Catalog::allowedCultures('Hydrit'))->toBe(['Meeresbewohner'])
        ->and(Catalog::allowedCultures('Techno'))->toBe(['Bunkermensch']);
    foreach (['Guul', 'Nosfera', 'Taratze', 'Wulfane'] as $race) {
        expect(Catalog::allowedCultures($race))->not->toContain('Volk der 13 Inseln')
            ->and(Catalog::allowedCultures($race))->not->toContain('Marsianische Städter');
    }
    expect(Catalog::allowedCultures('Barbar'))->toContain('Volk der 13 Inseln');
});

test('martian replacement deduplicates existing pool skills', function (): void {
    expect(Catalog::humanPool('Agarther'))->toContain('Nahkampf')
        ->and(Catalog::humanPool('Agarther'))->not->toContain('Feuerwaffen')
        ->and(Catalog::humanPool('Marsianer', 'Nahkampf'))->toHaveCount(6)
        ->and(Catalog::humanPool('Marsianer', 'Bildung'))->toHaveCount(5)
        ->and(Catalog::MARTIAN_BONUS_SKILLS)->toBe(['Pilot', 'Wissenschaftler', 'Athletik', 'Unterhalten'])
        ->and(Catalog::cultures()[Catalog::MARTIAN_CULTURE]['skills'])->toBe(['Bildung' => 1, 'Techniker' => 1]);
});
