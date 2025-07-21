<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DownloadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function actingMember(int $points = 0): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }
        return $user;
    }

    public function test_download_requires_enough_points(): void
    {
        $user = $this->actingMember(2);
        $this->actingAs($user);

        $response = $this->from('/downloads')->get('/downloads/download/BauanleitungEuphoriewurmV2.pdf');

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_download_fails_when_file_missing(): void
    {
        $user = $this->actingMember(20);
        $this->actingAs($user);

        $response = $this->from('/downloads')->get('/downloads/download/BauanleitungEuphoriewurmV2.pdf');

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_download_successful_when_file_exists_and_points_sufficient(): void
    {
        $user = $this->actingMember(20);
        $this->actingAs($user);

        Storage::disk('private')->put('downloads/BauanleitungProtoV11.pdf', 'dummy');

        $response = $this->get('/downloads/download/BauanleitungProtoV11.pdf');

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_index_displays_downloads_and_user_points(): void
    {
        $user = $this->actingMember(7);
        $this->actingAs($user);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertViewHas('downloads');
        $response->assertViewHas('userPoints', 7);
        $response->assertSee('Deine Baxx');
    }
}
