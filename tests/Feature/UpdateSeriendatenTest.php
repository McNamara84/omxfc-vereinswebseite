<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Profile\UpdateSeriendatenForm;

class UpdateSeriendatenTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_seriendaten_are_available(): void
    {
        $this->actingAs($user = User::factory()->create([
            'einstiegsroman' => '1 - Roman',
            'lesestand' => '2 - Roman',
            'lieblingsroman' => '3 - Roman',
            'lieblingsfigur' => 'Figur',
            'lieblingsmutation' => 'Mutation',
            'lieblingsschauplatz' => 'Ort',
            'lieblingsautor' => 'Autor',
            'lieblingszyklus' => 'Zyklus',
        ]));

        $component = Livewire::test(UpdateSeriendatenForm::class);

        $this->assertSame('1 - Roman', $component->get('state.einstiegsroman'));
        $this->assertSame('2 - Roman', $component->get('state.lesestand'));
        $this->assertSame('3 - Roman', $component->get('state.lieblingsroman'));
        $this->assertSame('Figur', $component->get('state.lieblingsfigur'));
        $this->assertSame('Mutation', $component->get('state.lieblingsmutation'));
        $this->assertSame('Ort', $component->get('state.lieblingsschauplatz'));
        $this->assertSame('Autor', $component->get('state.lieblingsautor'));
        $this->assertSame('Zyklus', $component->get('state.lieblingszyklus'));
    }

    public function test_seriendaten_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateSeriendatenForm::class)
            ->set('state', [
                'einstiegsroman' => 'A',
                'lesestand' => 'B',
                'lieblingsroman' => 'C',
                'lieblingsfigur' => 'D',
                'lieblingsmutation' => 'E',
                'lieblingsschauplatz' => 'F',
                'lieblingsautor' => 'G',
                'lieblingszyklus' => 'H',
            ])
            ->call('updateSeriendaten');

        $user->refresh();

        $this->assertSame('A', $user->einstiegsroman);
        $this->assertSame('B', $user->lesestand);
        $this->assertSame('C', $user->lieblingsroman);
        $this->assertSame('D', $user->lieblingsfigur);
        $this->assertSame('E', $user->lieblingsmutation);
        $this->assertSame('F', $user->lieblingsschauplatz);
        $this->assertSame('G', $user->lieblingsautor);
        $this->assertSame('H', $user->lieblingszyklus);
    }

    public function test_validation_fails_for_too_long_values(): void
    {
        $this->actingAs($user = User::factory()->create());

        $long = str_repeat('x', 256);

        Livewire::test(UpdateSeriendatenForm::class)
            ->set('state', ['einstiegsroman' => $long])
            ->call('updateSeriendaten')
            ->assertHasErrors(['einstiegsroman']);

        $this->assertNull($user->fresh()->einstiegsroman);
    }
}
