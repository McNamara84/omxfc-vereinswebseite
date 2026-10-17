<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Tests\TestCase;

class MaryAccessibleFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag);
        $component = new class extends Component {};
        $component->setId('mary-label-fixture');
        View::share('__livewire', $component);
        View::share('_instance', $component);
    }

    public function test_real_mary_controls_have_the_visible_label_as_accessible_name(): void
    {
        foreach (['input', 'password', 'select', 'textarea', 'file'] as $type) {
            $component = new class extends Component {};
            $component->setId('mary-label-fixture');
            $html = Blade::render('<x-mary-'.$type.' label="Sichtbare Beschriftung" wire:model="photo" />', ['__livewire' => $component]);
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $controls = (new DOMXPath($dom))->query('//input[not(@type="hidden")]|//select|//textarea');
            $this->assertGreaterThan(0, $controls->length, $type);
            $this->assertSame('Sichtbare Beschriftung', $controls->item(0)->getAttribute('aria-label'), $type);
        }
    }

    public function test_explicit_accessible_names_are_preserved_and_popover_ids_are_unique(): void
    {
        $html = Blade::render('<x-mary-input label="Feld" aria-label="Abweichend" popover="Erste Hilfe" /><x-mary-input label="Feld" aria-labelledby="anderes-label" popover="Zweite Hilfe" />');
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);
        $this->assertSame('Abweichend', $xpath->query('//input')->item(0)->getAttribute('aria-label'));
        $this->assertSame('anderes-label', $xpath->query('//input')->item(1)->getAttribute('aria-labelledby'));
        $ids = [];
        foreach ($xpath->query('//*[@role="tooltip"]') as $tooltip) {
            $ids[] = $tooltip->getAttribute('id');
        }
        $this->assertCount(2, $ids);
        $this->assertNotSame($ids[0], $ids[1]);
    }

    public function test_server_rendered_select_keeps_its_name_selection_and_disabled_options(): void
    {
        $html = Blade::render('<x-form-select name="condition" label="Zustand" value="0" :options="$options" />', [
            'options' => [['id' => 0, 'name' => 'Neu'], ['id' => 1, 'name' => 'Beschädigt', 'disabled' => true]],
        ]);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);

        $this->assertSame('Zustand', $xpath->query('//select')->item(0)->getAttribute('aria-label'));
        $this->assertSame('0', $xpath->query('//option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('1', $xpath->query('//option[@disabled]')->item(0)->getAttribute('value'));

        $html = Blade::render('<x-form-select name="condition" label="Zustand" aria-labelledby="condition-label" />');
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $select = $dom->getElementsByTagName('select')->item(0);
        $this->assertSame('condition-label', $select->getAttribute('aria-labelledby'));
        $this->assertFalse($select->hasAttribute('aria-label'));
    }
}
