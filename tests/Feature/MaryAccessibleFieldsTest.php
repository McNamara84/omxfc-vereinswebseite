<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\Concerns\HasAccessibleLabel;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Tests\Fixtures\RenderableField;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    View::share('errors', new ViewErrorBag);
    $component = new class extends Component {};
    $component->setId('mary-label-fixture');
    View::share('__livewire', $component);
    View::share('_instance', $component);
});

test('real mary controls have the visible label as accessible name', function (): void {
    foreach (['input', 'password', 'select', 'textarea', 'file'] as $type) {
        $component = new class extends Component {};
        $component->setId('mary-label-fixture');
        $html = Blade::render('<x-mary-'.$type.' label="Sichtbare Beschriftung" wire:model="photo" />', ['__livewire' => $component]);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $controls = (new DOMXPath($dom))->query('//input[not(@type="hidden")]|//select|//textarea');
        expect($controls->length)->toBeGreaterThan(0)
            ->and($controls->item(0)->getAttribute('aria-label'))->toBe('Sichtbare Beschriftung');
    }
});

test('explicit accessible names are preserved and popover ids are unique', function (): void {
    $html = Blade::render('<x-mary-input label="Feld" aria-label="Abweichend" popover="Erste Hilfe" /><x-mary-input label="Feld" aria-labelledby="anderes-label" popover="Zweite Hilfe" />');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//input')->item(0)->getAttribute('aria-label'))->toBe('Abweichend')
        ->and($xpath->query('//input')->item(1)->getAttribute('aria-labelledby'))->toBe('anderes-label');
    $ids = [];
    foreach ($xpath->query('//*[@role="tooltip"]') as $tooltip) {
        $ids[] = $tooltip->getAttribute('id');
    }
    expect($ids)->toHaveCount(2)
        ->and($ids[1])->not->toBe($ids[0]);
});

test('server rendered select keeps its name selection and disabled options', function (): void {
    $html = Blade::render('<x-form-select name="condition" label="Zustand" value="0" :options="$options" />', ['options' => [['id' => 0, 'name' => 'Neu'], ['id' => 1, 'name' => 'Beschädigt', 'disabled' => true]]]);
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//select')->item(0)->getAttribute('aria-label'))->toBe('Zustand')
        ->and($xpath->query('//option[@selected]')->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query('//option[@disabled]')->item(0)->getAttribute('value'))->toBe('1');
    $html = Blade::render('<x-form-select name="condition" label="Zustand" aria-labelledby="condition-label" />');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $select = $dom->getElementsByTagName('select')->item(0);
    expect($select->getAttribute('aria-labelledby'))->toBe('condition-label')
        ->and($select->hasAttribute('aria-label'))->toBeFalse();
});

test('server rendered select preserves help references and links its feedback', function (?string $fieldId, ?string $describedBy, bool $hasError): void {
    if ($hasError) {
        View::share('errors', (new ViewErrorBag)->put('default', new MessageBag(['condition' => ['Bitte Zustand auswählen.']])));
    }
    $html = Blade::render('<p id="condition-help">Hinweis zum Zustand</p><p id="condition-note">Weitere Angaben</p>'.'<x-form-select :id="$fieldId" name="condition" label="Zustand" :aria-describedby="$describedBy" />', compact('fieldId', 'describedBy'));
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $select = $dom->getElementsByTagName('select')->item(0);
    $feedbackId = ($fieldId ?? 'select-condition').'-feedback';
    $expected = trim(($describedBy ?? '').' '.$feedbackId);
    expect($select->getAttribute('aria-describedby'))->toBe($expected)
        ->and($select->getAttribute('aria-invalid'))->toBe($hasError ? 'true' : 'false');
    foreach (explode(' ', $expected) as $id) {
        expect($dom->getElementById($id))->not->toBeNull();
    }
    expect(trim($dom->getElementById($feedbackId)->textContent))->toBe($hasError ? 'Bitte Zustand auswählen.' : '');
})->with([[null, null, false], ['condition-choice', null, true], ['condition-choice', 'condition-help condition-note', false], ['condition-choice', 'condition-help condition-note', true], [null, 'condition-help condition-note', true], ['condition-choice', '', true]]);

test('existing help is only extended by present errors and hints', function (): void {
    $html = Blade::render('<x-mary-input id="street" label="Straße" aria-describedby="street-help" wire:model="street" />');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $input = $dom->getElementsByTagName('input')->item(0);
    expect($input->getAttribute('aria-describedby'))->toBe('street-help')
        ->and($input->getAttribute('aria-invalid'))->toBe('false');
    View::share('errors', (new ViewErrorBag)->put('default', new MessageBag(['street' => ['Bitte Straße angeben.']])));
    $html = Blade::render('<x-mary-input id="street" label="Straße" hint="Ohne Hausnummer" aria-describedby="street-help" wire:model="street" />');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $input = $dom->getElementsByTagName('input')->item(0);
    expect($input->getAttribute('aria-describedby'))->toBe('street-help street-error street-hint')
        ->and($input->getAttribute('aria-invalid'))->toBe('true');
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@id="street-error"]')->item(0)->textContent)->toContain('Bitte Straße angeben.')
        ->and(trim($xpath->query('//*[@id="street-hint"]')->item(0)->textContent))->toBe('Ohne Hausnummer');
    $html = Blade::render('<x-mary-input id="street" label="Straße" aria-describedby="street-help" wire:model="street" omit-error />');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $input = $dom->getElementsByTagName('input')->item(0);
    expect($input->getAttribute('aria-describedby'))->toBe('street-help')
        ->and($input->getAttribute('aria-invalid'))->toBe('false');
});

test('view and closure templates are preserved by the field adapter', function (): void {
    foreach ([view('components.ui.breadcrumbs', ['items' => []]), static fn (): string => '<input>'] as $output) {
        $component = new class($output) extends RenderableField
        {
            use HasAccessibleLabel;
        };
        expect($component->render())->toBe($output);
    }
});
