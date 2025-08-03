<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_see_termine_link_in_veranstaltungen_menu(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('termine'));
    }
}
