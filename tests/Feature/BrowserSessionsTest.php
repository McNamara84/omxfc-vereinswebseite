<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Profile\LogoutOtherBrowserSessionsForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('other browser sessions can be logged out', function (): void {
    $this->actingAs(User::factory()->create());
    Livewire::test(LogoutOtherBrowserSessionsForm::class)->set('password', 'password')->call('logoutOtherBrowserSessions')->assertSuccessful();
});
