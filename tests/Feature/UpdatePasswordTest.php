<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('password can be updated', function (): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdatePasswordForm::class)->set('state', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->call('updatePassword');
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('password form uses mary password help', function (): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdatePasswordForm::class)->assertSee('Mindestens 8 Zeichen.')->assertSee('Verwende mindestens 8 Zeichen und ein nur hier genutztes Passwort.');
});

test('current password must be correct', function (): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdatePasswordForm::class)->set('state', ['current_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->call('updatePassword')->assertHasErrors(['current_password']);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('new passwords must match', function (): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(UpdatePasswordForm::class)->set('state', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'wrong-password'])->call('updatePassword')->assertHasErrors(['password']);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
