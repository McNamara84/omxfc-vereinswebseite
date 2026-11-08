<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Profile\UpdateSeriendatenForm;
use App\Models\User;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('current seriendaten are available', function (): void {
    $this->actingAs($user = User::factory()->create(['einstiegsroman' => '1 - Roman', 'lesestand' => '2 - Roman', 'lieblingsroman' => '3 - Roman', 'lieblingsfigur' => 'Figur', 'lieblingsmutation' => 'Mutation', 'lieblingsschauplatz' => 'Ort', 'lieblingsautor' => 'Autor', 'lieblingszyklus' => 'Zyklus', 'lieblingsthema' => 'Thema', 'lieblingshardcover' => 'HC', 'lieblingscover' => 'MX 1 Roman']));
    $component = Livewire::test(UpdateSeriendatenForm::class);
    expect($component->get('state.einstiegsroman'))->toBe('1 - Roman')
        ->and($component->get('state.lesestand'))->toBe('2 - Roman')
        ->and($component->get('state.lieblingsroman'))->toBe('3 - Roman')
        ->and($component->get('state.lieblingsfigur'))->toBe('Figur')
        ->and($component->get('state.lieblingsmutation'))->toBe('Mutation')
        ->and($component->get('state.lieblingsschauplatz'))->toBe('Ort')
        ->and($component->get('state.lieblingsautor'))->toBe('Autor')
        ->and($component->get('state.lieblingszyklus'))->toBe('Zyklus')
        ->and($component->get('state.lieblingsthema'))->toBe('Thema')
        ->and($component->get('state.lieblingshardcover'))->toBe('HC')
        ->and($component->get('state.lieblingscover'))->toBe('MX 1 Roman');
});

test('seriendaten can be updated', function (): void {
    $fields = ['einstiegsroman', 'lesestand', 'lieblingsroman', 'lieblingsfigur', 'lieblingsmutation', 'lieblingsschauplatz', 'lieblingsautor', 'lieblingszyklus', 'lieblingsthema', 'lieblingshardcover', 'lieblingscover'];
    $options = array_combine($fields, array_map(fn ($value) => [$value], range('A', 'K')));
    unset($options['lieblingsmutation']);
    $this->mock(SeriesProfileOptions::class)->shouldReceive('all')->andReturn($options);
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdateSeriendatenForm::class)->set('state', ['einstiegsroman' => 'A', 'lesestand' => 'B', 'lieblingsroman' => 'C', 'lieblingsfigur' => 'D', 'lieblingsmutation' => 'E', 'lieblingsschauplatz' => 'F', 'lieblingsautor' => 'G', 'lieblingszyklus' => 'H', 'lieblingsthema' => 'I', 'lieblingshardcover' => 'J', 'lieblingscover' => 'K'])->call('updateSeriendaten');
    $user->refresh();
    expect($user->einstiegsroman)->toBe('A')
        ->and($user->lesestand)->toBe('B')
        ->and($user->lieblingsroman)->toBe('C')
        ->and($user->lieblingsfigur)->toBe('D')
        ->and($user->lieblingsmutation)->toBe('E')
        ->and($user->lieblingsschauplatz)->toBe('F')
        ->and($user->lieblingsautor)->toBe('G')
        ->and($user->lieblingszyklus)->toBe('H')
        ->and($user->lieblingsthema)->toBe('I')
        ->and($user->lieblingshardcover)->toBe('J')
        ->and($user->lieblingscover)->toBe('K');
});

test('validation fails for too long values', function (): void {
    $this->actingAs($user = User::factory()->create());
    $long = str_repeat('x', 256);
    Livewire::test(UpdateSeriendatenForm::class)->set('state', ['einstiegsroman' => $long])->call('updateSeriendaten')->assertHasErrors(['einstiegsroman']);
    expect($user->fresh()->einstiegsroman)->toBeNull();
});
