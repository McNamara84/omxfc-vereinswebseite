<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('current profile information is available', function (): void {
    $this->actingAs($user = User::factory()->create());
    $component = Livewire::test(UpdateProfileInformationForm::class);
    expect($component->state['vorname'])->toEqual($user->vorname)
        ->and($component->state['nachname'])->toEqual($user->nachname)
        ->and($component->state['email'])->toEqual($user->email);
});

test('profile information can be updated', function (): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdateProfileInformationForm::class)->set('state', ['vorname' => 'Test', 'nachname' => 'Name', 'strasse' => 'Teststraße', 'hausnummer' => '5', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'telefon' => '01234', 'mitgliedsbeitrag' => 12.0, 'email' => 'new@example.com'])->call('updateProfileInformation');
    expect($user->fresh()->vorname)->toEqual('Test')
        ->and($user->fresh()->nachname)->toEqual('Name')
        ->and($user->fresh()->email)->toEqual('new@example.com');
});
