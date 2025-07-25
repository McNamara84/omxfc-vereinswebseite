<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\File;

class StatistikTest extends TestCase
{
    use RefreshDatabase;

     private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }

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
            [
                'nummer'    => 1,
                'titel'     => 'Roman1',
                'text'      => ['Author1'],
                'bewertung' => 4.0,
                'stimmen'   => 10,
                'personen'  => ['Char1', 'Char2'],
            ],
            [
                'nummer'    => 2,
                'titel'     => 'Roman2',
                'text'      => ['Author1', 'Author2'],
                'bewertung' => 5.0,
                'stimmen'   => 20,
                'personen'  => ['Char2', 'Char3'],
            ],
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

    public function test_top_author_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(10);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top 10 Autor');
        $response->assertSee('Author2');
    }

    public function test_top_author_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(7);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('8');
    }

    public function test_statistics_page_returns_500_when_file_missing(): void
    {
        $user = $this->actingMemberWithPoints(5);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertStatus(500);
    }

    public function test_teamplayer_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(4);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top Teamplayer');
        $response->assertSee('Author2');
    }

    public function test_teamplayer_table_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(1);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Top Teamplayer');
    }

    public function test_character_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(16);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top 10 Charaktere');
        $response->assertSee('Char2');
    }

    public function test_character_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(9);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('10');
    }
}
