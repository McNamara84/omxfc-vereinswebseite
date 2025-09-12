<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProtokolleTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_protokolle_page_is_accessible(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle');

        $response->assertOk();
        $response->assertSee('Gründungsversammlung');
    }

    public function test_protokoll_can_be_downloaded_when_file_exists(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('protokolle/test.pdf', 'dummy');

        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle/download/test.pdf');

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_error_when_protokoll_is_missing(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->from('/protokolle')->get('/protokolle/download/missing.pdf');

        $response->assertRedirect('/protokolle');
        $response->assertSessionHasErrors();
    }
}
