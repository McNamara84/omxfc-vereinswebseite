<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserSeriendaten;
use App\Livewire\Profile\UpdateSeriendatenForm;
use App\Models\User;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(SeriesProfileTestFixtures::class);

test('series form copy uses the computed completion total', function (int $total): void {
    $this->view('profile.update-seriendaten-form', ['fields' => [], 'fieldOptions' => [], 'unavailable' => [], 'completion' => ['filled' => 4, 'total' => $total, 'missing' => []]])->assertSeeText("4 von {$total} Angaben gespeichert.")->assertSeeText("Für den Abschluss der Profilpflege-Tour müssen alle {$total} Felder ausgefüllt und gespeichert sein.");
})->with([[8], [13]]);

test('catalog options exclude missing values and normalize concrete choices', function (): void {
    $this->catalog([['nummer' => 1, 'titel' => 'Roman ohne Metadaten'], ['nummer' => 2, 'titel' => 'Roman mit Metadaten', 'zyklus' => ' Zyklus ', 'text' => [null, '', ' ', ' Autor ', 'Autor'], 'personen' => [null, 'Figur'], 'orte' => ['Ort', ''], 'schlagworte' => [null, '', 'Thema', '0', 42]]]);
    $options = app(SeriesProfileOptions::class)->all();
    expect($options['lieblingsautor'])->toBe(['Autor'])
        ->and($options['lieblingszyklus'])->toBe(['Zyklus'])
        ->and($options['lieblingsfigur'])->toBe(['Figur'])
        ->and($options['lieblingsschauplatz'])->toBe(['Ort'])
        ->and($options['lieblingsthema'])->toBe(['0', 'Thema']);
    $user = User::factory()->create();
    app(UpdateUserSeriendaten::class)->update($user, ['lieblingsautor' => 'Autor', 'lieblingsthema' => 'Thema']);
    expect($user->lieblingsautor)->toBe('Autor')
        ->and($user->lieblingsthema)->toBe('Thema');
});

test('catalog with only missing metadata produces empty option lists', function (): void {
    $this->catalog([['nummer' => 1, 'titel' => 'Roman ohne Metadaten']]);
    $options = app(SeriesProfileOptions::class)->all();
    foreach (['lieblingsautor', 'lieblingszyklus', 'lieblingsfigur', 'lieblingsschauplatz', 'lieblingsthema'] as $field) {
        expect($options[$field])->toBeEmpty();
    }
});

test('optional partial save preserves other fields and never awards baxx', function (): void {
    $this->stubOptions(['einstiegsroman' => ['1 - Der Gott aus dem Eis']]);
    $user = User::factory()->create(['lesestand' => 'Historischer Lesestand']);
    app(UpdateUserSeriendaten::class)->update($user, ['einstiegsroman' => '1 - Der Gott aus dem Eis', 'lieblingsmutation' => '  Mein Favorit ']);
    expect($user->lesestand)->toBe('Historischer Lesestand')
        ->and($user->lieblingsmutation)->toBe('Mein Favorit');
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
    expect(app(SeriesProfileCompletion::class)->forUser($user)['filled'])->toBe(3);
});

test('historical choice is displayed and can be saved without catalog', function (): void {
    $this->stubOptions();
    $user = User::factory()->create(['einstiegsroman' => 'Historischer Roman']);
    $this->actingAs($user);
    Livewire::test(UpdateSeriendatenForm::class)->assertSee('Historischer Roman')->assertSee('Lieblingsmutation (optional)')->assertSee('Auswahllisten sind derzeit nicht verfügbar')->call('updateSeriendaten')->assertHasNoErrors()->assertDispatched('profile-series-saved');
    expect($user->fresh()->einstiegsroman)->toBe('Historischer Roman');
});

test('changed unknown dropdown value is rejected and not saved', function (): void {
    $this->stubOptions(['einstiegsroman' => ['Gültiger Roman']]);
    $user = User::factory()->create(['einstiegsroman' => 'Historischer Roman']);
    try {
        app(UpdateUserSeriendaten::class)->update($user, ['einstiegsroman' => 'Manipulierter Roman']);
        $this->fail('Unknown selection should be rejected.');
    } catch (ValidationException $error) {
        expect($error->errors())->toHaveKey('einstiegsroman');
    }
    expect($user->fresh()->einstiegsroman)->toBe('Historischer Roman');
});

test('mutation validation and whitespace normalization', function (): void {
    $this->stubOptions();
    $user = User::factory()->create();
    $this->actingAs($user);
    Livewire::test(UpdateSeriendatenForm::class)->set('state.lieblingsmutation', str_repeat('x', 256))->call('updateSeriendaten')->assertHasErrors('lieblingsmutation');
    app(UpdateUserSeriendaten::class)->update($user, ['lieblingsmutation' => " \t "]);
    expect($user->lieblingsmutation)->toBeNull()
        ->and(SeriesProfileCompletion::normalize('0'))->toBe('0')
        ->and(SeriesProfileCompletion::normalize(42))->toBe(42);
});
