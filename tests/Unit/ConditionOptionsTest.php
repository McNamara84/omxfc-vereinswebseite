<?php

namespace Tests\Unit;

use App\Support\ConditionOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ConditionOptions::class)]
class ConditionOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
    }
    public function test_standard_returns_seven_options(): void
    {
        $options = ConditionOptions::standard();

        $this->assertCount(7, $options);

        $ids = array_column($options, 'id');
        $this->assertSame(['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3'], $ids);
    }

    public function test_full_returns_nine_options(): void
    {
        $options = ConditionOptions::full();

        $this->assertCount(9, $options);

        $ids = array_column($options, 'id');
        $this->assertSame(['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3', 'Z3-4', 'Z4'], $ids);
    }

    public function test_with_same_option_prepends_empty_id_option(): void
    {
        $options = ConditionOptions::withSameOption();

        $this->assertCount(10, $options);
        $this->assertSame('', $options[0]['id']);
        $this->assertStringContainsString('Gleicher Zustand', $options[0]['name']);
    }

    public function test_all_options_have_id_and_name_keys(): void
    {
        foreach (ConditionOptions::full() as $option) {
            $this->assertArrayHasKey('id', $option);
            $this->assertArrayHasKey('name', $option);
        }
    }

    public function test_option_names_start_with_condition_code(): void
    {
        foreach (ConditionOptions::full() as $option) {
            $this->assertStringStartsWith($option['id'] . ' - ', $option['name']);
        }
    }

    public function test_intermediate_conditions_have_title_tooltip(): void
    {
        $intermediateIds = ['Z0-1', 'Z1-2', 'Z2-3', 'Z3-4'];

        foreach (ConditionOptions::full() as $option) {
            if (in_array($option['id'], $intermediateIds)) {
                $this->assertArrayHasKey('title', $option, "Zwischenwert {$option['id']} sollte einen Tooltip-Title haben.");
                $this->assertNotEmpty($option['title']);
            }
        }
    }

    public function test_main_conditions_have_no_title(): void
    {
        $mainIds = ['Z0', 'Z1', 'Z2', 'Z3', 'Z4'];

        foreach (ConditionOptions::full() as $option) {
            if (in_array($option['id'], $mainIds)) {
                $this->assertArrayNotHasKey('title', $option, "Hauptzustand {$option['id']} sollte keinen Title haben.");
            }
        }
    }

    public function test_full_extends_standard(): void
    {
        $standard = ConditionOptions::standard();
        $full = ConditionOptions::full();

        // Die ersten 7 Einträge von full() müssen standard() entsprechen
        $this->assertSame($standard, array_slice($full, 0, 7));
    }

    public function test_with_same_option_contains_all_full_options(): void
    {
        $full = ConditionOptions::full();
        $withSame = ConditionOptions::withSameOption();

        // Ab Index 1 müssen alle full()-Einträge enthalten sein
        $this->assertSame($full, array_slice($withSame, 1));
    }
}
