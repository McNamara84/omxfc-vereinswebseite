<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class StatistikTest extends TestCase
{
    use RefreshDatabase;

    private function actingMemberWithPoints(int $points): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        $user->incrementTeamPoints($points);
        return $user;
    }

    private function createDataFile(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => 'Roman1', 'text' => ['Author1'], 'bewertung' => 4.0, 'stimmen' => 10],
            ['nummer' => 2, 'titel' => 'Roman2', 'text' => ['Author1', 'Author2'], 'bewertung' => 5.0, 'stimmen' => 20],
        ];
        $path = storage_path('app/private/maddrax.json');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    public function test_statistics_page_shows_computed_values(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(5); // show all cards
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('4,50');
        $response->assertSee('30');
        $response->assertSee('15,00');
        $response->assertSee('Roman2');
    }
}
