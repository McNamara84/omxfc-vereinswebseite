<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserSeriendaten;
use App\Livewire\Profile\UpdateSeriendatenForm;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SeriesProfileTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([8])]
    #[TestWith([13])]
    public function test_series_form_copy_uses_the_computed_completion_total(int $total): void
    {
        $this->view('profile.update-seriendaten-form', [
            'fields' => [],
            'fieldOptions' => [],
            'unavailable' => [],
            'completion' => ['filled' => 4, 'total' => $total, 'missing' => []],
        ])->assertSeeText("4 von {$total} Angaben gespeichert.")
            ->assertSeeText("Für den Abschluss der Profilpflege-Tour müssen alle {$total} Felder ausgefüllt und gespeichert sein.");
    }

    private function stubOptions(array $values = []): void
    {
        $options = array_fill_keys(array_diff(array_keys(SeriesProfileCompletion::FIELDS), ['lieblingsmutation']), []);
        $this->mock(SeriesProfileOptions::class)->shouldReceive('all')->andReturn(array_replace($options, $values));
    }

    public function test_catalog_options_exclude_missing_values_and_normalize_concrete_choices(): void
    {
        $this->catalog([
            ['nummer' => 1, 'titel' => 'Roman ohne Metadaten'],
            [
                'nummer' => 2, 'titel' => 'Roman mit Metadaten', 'zyklus' => ' Zyklus ',
                'text' => [null, '', "\u{00a0}", ' Autor ', 'Autor'],
                'personen' => [null, 'Figur'], 'orte' => ['Ort', ''],
                'schlagworte' => [null, '', 'Thema', '0', 42],
            ],
        ]);

        $options = app(SeriesProfileOptions::class)->all();
        $this->assertSame(['Autor'], $options['lieblingsautor']);
        $this->assertSame(['Zyklus'], $options['lieblingszyklus']);
        $this->assertSame(['Figur'], $options['lieblingsfigur']);
        $this->assertSame(['Ort'], $options['lieblingsschauplatz']);
        $this->assertSame(['0', 'Thema'], $options['lieblingsthema']);

        $user = User::factory()->create();
        app(UpdateUserSeriendaten::class)->update($user, ['lieblingsautor' => 'Autor', 'lieblingsthema' => 'Thema']);
        $this->assertSame('Autor', $user->lieblingsautor);
        $this->assertSame('Thema', $user->lieblingsthema);
    }

    public function test_catalog_with_only_missing_metadata_produces_empty_option_lists(): void
    {
        $this->catalog([['nummer' => 1, 'titel' => 'Roman ohne Metadaten']]);
        $options = app(SeriesProfileOptions::class)->all();
        foreach (['lieblingsautor', 'lieblingszyklus', 'lieblingsfigur', 'lieblingsschauplatz', 'lieblingsthema'] as $field) {
            $this->assertSame([], $options[$field], $field);
        }
    }

    private function catalog(array $rows): void
    {
        DB::transaction(fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate(
            (string) Str::uuid(), ['maddrax' => json_encode($rows, JSON_THROW_ON_ERROR)],
        ));
    }

    public function test_optional_partial_save_preserves_other_fields_and_never_awards_baxx(): void
    {
        $this->stubOptions(['einstiegsroman' => ['1 - Der Gott aus dem Eis']]);
        $user = User::factory()->create(['lesestand' => 'Historischer Lesestand']);
        app(UpdateUserSeriendaten::class)->update($user, ['einstiegsroman' => '1 - Der Gott aus dem Eis', 'lieblingsmutation' => "  Mein Favorit\u{00a0}"]);
        $this->assertSame('Historischer Lesestand', $user->lesestand);
        $this->assertSame('Mein Favorit', $user->lieblingsmutation);
        $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
        $this->assertSame(3, app(SeriesProfileCompletion::class)->forUser($user)['filled']);
    }

    public function test_historical_choice_is_displayed_and_can_be_saved_without_catalog(): void
    {
        $this->stubOptions();
        $user = User::factory()->create(['einstiegsroman' => 'Historischer Roman']);
        $this->actingAs($user);
        Livewire::test(UpdateSeriendatenForm::class)->assertSee('Historischer Roman')->assertSee('Lieblingsmutation (optional)')
            ->assertSee('Auswahllisten sind derzeit nicht verfügbar')->call('updateSeriendaten')->assertHasNoErrors()
            ->assertDispatched('profile-series-saved');
        $this->assertSame('Historischer Roman', $user->fresh()->einstiegsroman);
    }

    public function test_changed_unknown_dropdown_value_is_rejected_and_not_saved(): void
    {
        $this->stubOptions(['einstiegsroman' => ['Gültiger Roman']]);
        $user = User::factory()->create(['einstiegsroman' => 'Historischer Roman']);
        try {
            app(UpdateUserSeriendaten::class)->update($user, ['einstiegsroman' => 'Manipulierter Roman']);
            $this->fail('Unknown selection should be rejected.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('einstiegsroman', $error->errors());
        }
        $this->assertSame('Historischer Roman', $user->fresh()->einstiegsroman);
    }

    public function test_mutation_validation_and_whitespace_normalization(): void
    {
        $this->stubOptions();
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(UpdateSeriendatenForm::class)
            ->set('state.lieblingsmutation', str_repeat('x', 256))->call('updateSeriendaten')->assertHasErrors('lieblingsmutation');
        app(UpdateUserSeriendaten::class)->update($user, ['lieblingsmutation' => " \t\u{00a0}"]);
        $this->assertNull($user->lieblingsmutation);
        $this->assertSame('0', SeriesProfileCompletion::normalize('0'));
        $this->assertSame(42, SeriesProfileCompletion::normalize(42));
    }
}
