<?php

namespace Tests\Unit;

use App\Support\RpgCharEditorRuleCatalog as Catalog;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;

class RpgCharEditorRuleCatalogTest extends TestCase
{
    public function test_javascript_fixture_matches_the_shared_server_definitions(): void
    {
        $this->assertSame([
            'ruleCatalog' => Catalog::ruleConfig(),
            'trainingRules' => RpgCharEditorTraining::ruleConfig(),
        ], json_decode(file_get_contents(__DIR__.'/../Fixtures/rpg-extension-rules.json'), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_sources_are_complete_and_disabled_expansions_are_not_snapshotted(): void
    {
        $this->assertTrue(Catalog::sources()['expansion-1']['defaultEnabled']);
        $this->assertSame(['base'], array_column(Catalog::snapshots([]), 'id'));
        $this->assertSame(['base'], array_column(Catalog::snapshots(['expansion-1' => false]), 'id'));
        $snapshots = Catalog::snapshots(['expansion-1' => true]);
        $this->assertSame(['base', 'expansion-1'], array_column($snapshots, 'id'));
        $this->assertSame('Stefan Küppers', $snapshots[1]['author']);
        $this->assertArrayNotHasKey('defaultEnabled', $snapshots[1]);
        foreach ([...Catalog::races(), ...Catalog::cultures(), ...RpgCharEditorTraining::definitions()] as $definition) {
            $this->assertArrayHasKey($definition['source'], Catalog::sources());
        }
    }

    public function test_exclusive_cultures_and_baseline_restrictions_remain_consistent(): void
    {
        $this->assertSame(['Ruinenbewohner'], Catalog::allowedCultures('Morlock'));
        $this->assertSame(['Marsianische Städter'], Catalog::allowedCultures('Marsianer'));
        $this->assertSame(['Mensch des 21. Jahrhunderts'], Catalog::allowedCultures('Agarther'));
        $this->assertSame(['Meeresbewohner'], Catalog::allowedCultures('Hydrit'));
        $this->assertSame(['Bunkermensch'], Catalog::allowedCultures('Techno'));
        foreach (['Guul', 'Nosfera', 'Taratze', 'Wulfane'] as $race) {
            $this->assertNotContains('Volk der 13 Inseln', Catalog::allowedCultures($race));
            $this->assertNotContains('Marsianische Städter', Catalog::allowedCultures($race));
        }
        $this->assertContains('Volk der 13 Inseln', Catalog::allowedCultures('Barbar'));
    }

    public function test_martian_replacement_deduplicates_existing_pool_skills(): void
    {
        $this->assertContains('Nahkampf', Catalog::humanPool('Agarther'));
        $this->assertNotContains('Feuerwaffen', Catalog::humanPool('Agarther'));
        $this->assertCount(6, Catalog::humanPool('Marsianer', 'Nahkampf'));
        $this->assertCount(5, Catalog::humanPool('Marsianer', 'Bildung'));
        $this->assertSame(['Pilot', 'Wissenschaftler', 'Athletik', 'Unterhalten'], Catalog::MARTIAN_BONUS_SKILLS);
        $this->assertSame(['Bildung' => 1, 'Techniker' => 1], Catalog::cultures()[Catalog::MARTIAN_CULTURE]['skills']);
    }
}
