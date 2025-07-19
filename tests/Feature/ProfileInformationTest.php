<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->vorname, $component->state['vorname']);
        $this->assertEquals($user->nachname, $component->state['nachname']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', [
                'vorname' => 'Test',
                'nachname' => 'Name',
                'strasse' => 'Teststraße',
                'hausnummer' => '5',
                'plz' => '12345',
                'stadt' => 'Teststadt',
                'land' => 'Deutschland',
                'telefon' => '01234',
                'mitgliedsbeitrag' => 12.00,
                'email' => 'test@example.com',
            ])
            ->call('updateProfileInformation');

        $this->assertEquals('Test', $user->fresh()->vorname);
        $this->assertEquals('Name', $user->fresh()->nachname);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }
}
