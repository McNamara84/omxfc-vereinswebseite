<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\Profile\UpdateSeriendatenForm;
use App\Models\Book;
use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(UpdateSeriendatenFormTestFixtures::class);

beforeEach(function (): void {
    // Configure a separate storage path with test data
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    config(['filesystems.disks.local.root' => $this->testStoragePath.'/app/private']);
    // Reset cached data in the service
    $ref = new \ReflectionClass(MaddraxDataService::class);
    $property = $ref->getProperty('data');
    $property->setValue(null, null);
    // Minimal Maddrax data for the component
    $data = [['nummer' => 1, 'zyklus' => 'Z1', 'titel' => 'Roman1', 'text' => ['Author1', 'Author2'], 'personen' => ['Figur1'], 'orte' => ['Ort1'], 'schlagworte' => ['Thema1']], ['nummer' => 2, 'zyklus' => 'Z2', 'titel' => 'Roman2', 'text' => ['Author1'], 'personen' => ['Figur2'], 'orte' => ['Ort2'], 'schlagworte' => ['Thema2']]];
    File::put($this->testStoragePath.'/app/private/maddrax.json', json_encode($data));
    // Create some hardcover books
    Book::create(['roman_number' => 1, 'title' => 'Hardcover1', 'author' => 'Author1', 'type' => BookType::MaddraxHardcover]);
    Book::create(['roman_number' => 2, 'title' => 'Hardcover2', 'author' => 'Author2', 'type' => BookType::MaddraxHardcover]);
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('mount populates initial state and renders catalog options', function (): void {
    $user = $this->actingMember();
    $user->forceFill(['einstiegsroman' => '1 - Roman1', 'lesestand' => '1 - Roman1', 'lieblingsroman' => '2 - Roman2', 'lieblingsfigur' => 'Figur1', 'lieblingsmutation' => 'Mutation1', 'lieblingsschauplatz' => 'Ort1', 'lieblingsautor' => 'Author1', 'lieblingszyklus' => 'Z1', 'lieblingsthema' => 'Thema1', 'lieblingshardcover' => '1 - Hardcover1', 'lieblingscover' => 'MX 1 Roman1'])->save();
    $this->actingAs($user);
    $component = Livewire::test(UpdateSeriendatenForm::class)->assertSee('Einstiegsroman (optional)');
    $component->assertSet('state.einstiegsroman', '1 - Roman1')->assertSet('state.lesestand', '1 - Roman1')->assertSet('state.lieblingsroman', '2 - Roman2')->assertSet('state.lieblingsfigur', 'Figur1')->assertSet('state.lieblingsmutation', 'Mutation1')->assertSet('state.lieblingsschauplatz', 'Ort1')->assertSet('state.lieblingsautor', 'Author1')->assertSet('state.lieblingszyklus', 'Z1')->assertSet('state.lieblingsthema', 'Thema1')->assertSet('state.lieblingshardcover', '1 - Hardcover1')->assertSet('state.lieblingscover', 'MX 1 Roman1');
    $options = $component->viewData('fieldOptions');
    foreach (['einstiegsroman' => ['1 - Roman1', '2 - Roman2'], 'lesestand' => ['1 - Roman1', '2 - Roman2'], 'lieblingsroman' => ['1 - Roman1', '2 - Roman2'], 'lieblingsautor' => ['Author1', 'Author2'], 'lieblingszyklus' => ['Z1', 'Z2'], 'lieblingsfigur' => ['Figur1', 'Figur2'], 'lieblingsschauplatz' => ['Ort1', 'Ort2'], 'lieblingsthema' => ['Thema1', 'Thema2'], 'lieblingshardcover' => ['1 - Hardcover1', '2 - Hardcover2'], 'lieblingscover' => ['MX 1 Roman1', 'MX 2 Roman2', 'HC 1 Hardcover1', 'HC 2 Hardcover2']] as $field => $values) {
        expect($options[$field])->toBe($values);
        foreach ($values as $value) {
            $component->assertSeeHtml('value="'.$value.'"');
        }
    }
});

test('only profile state is serialized on mount validation and save', function (): void {
    $user = $this->actingMember();
    $component = Livewire::test(UpdateSeriendatenForm::class)->assertSee('2 - Roman2');
    expect(array_keys($component->snapshot['data']))->toBe(['state']);
    $component->set('state.einstiegsroman', 'Unbekannter Roman')->call('updateSeriendaten')->assertHasErrors('einstiegsroman')->assertSee('2 - Roman2');
    expect(array_keys($component->snapshot['data']))->toBe(['state']);
    $component->set('state.einstiegsroman', '1 - Roman1')->call('updateSeriendaten')->assertHasNoErrors()->assertDispatched('profile-series-saved')->assertSet('state.einstiegsroman', '1 - Roman1')->assertSee('2 - Roman2');
    expect(array_keys($component->snapshot['data']))->toBe(['state'])
        ->and($user->fresh()->einstiegsroman)->toBe('1 - Roman1');
});

test('seriendaten can be updated', function (): void {
    $user = $this->actingMember();
    $this->actingAs($user);
    Livewire::test(UpdateSeriendatenForm::class)->set('state', ['einstiegsroman' => '1 - Roman1', 'lesestand' => '2 - Roman2', 'lieblingsroman' => '2 - Roman2', 'lieblingsfigur' => 'Figur2', 'lieblingsmutation' => 'Mutation1', 'lieblingsschauplatz' => 'Ort2', 'lieblingsautor' => 'Author2', 'lieblingszyklus' => 'Z2', 'lieblingsthema' => 'Thema2', 'lieblingshardcover' => '2 - Hardcover2', 'lieblingscover' => 'HC 2 Hardcover2'])->call('updateSeriendaten')->assertHasNoErrors();
    $user->refresh();
    expect($user->einstiegsroman)->toBe('1 - Roman1')
        ->and($user->lesestand)->toBe('2 - Roman2')
        ->and($user->lieblingsroman)->toBe('2 - Roman2')
        ->and($user->lieblingsfigur)->toBe('Figur2')
        ->and($user->lieblingsmutation)->toBe('Mutation1')
        ->and($user->lieblingsschauplatz)->toBe('Ort2')
        ->and($user->lieblingsautor)->toBe('Author2')
        ->and($user->lieblingszyklus)->toBe('Z2')
        ->and($user->lieblingsthema)->toBe('Thema2')
        ->and($user->lieblingshardcover)->toBe('2 - Hardcover2')
        ->and($user->lieblingscover)->toBe('HC 2 Hardcover2');
});
