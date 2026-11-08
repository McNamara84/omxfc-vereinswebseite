<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('user accounts can be deleted', function (): void {
    if (! Features::hasAccountDeletionFeatures()) {
        $this->markTestSkipped('Account deletion is not enabled.');
    }
    $this->actingAs($user = User::factory()->create());
    $component = Livewire::test(DeleteUserForm::class)->set('password', 'password')->call('deleteUser');
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided before account can be deleted', function (): void {
    if (! Features::hasAccountDeletionFeatures()) {
        $this->markTestSkipped('Account deletion is not enabled.');
    }
    $this->actingAs($user = User::factory()->create());
    Livewire::test(DeleteUserForm::class)->set('password', 'wrong-password')->call('deleteUser')->assertHasErrors(['password']);
    expect($user->fresh())->not->toBeNull();
});
