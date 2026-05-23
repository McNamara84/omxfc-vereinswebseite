<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm;
use Tests\TestCase;

class BrowserSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_browser_sessions_can_be_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $session = app('session.store');
        $session->start();

        $request = request()->duplicate();
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        $guard = $this->mock(StatefulGuard::class);
        $guard->shouldReceive('logoutOtherDevices')
            ->once()
            ->with('password');

        $component = app(LogoutOtherBrowserSessionsForm::class);
        $component->password = 'password';

        $component->logoutOtherBrowserSessions($guard);

        $this->assertSame(
            $user->getAuthPassword(),
            $session->get('password_hash_'.Auth::getDefaultDriver()),
        );
    }
}
